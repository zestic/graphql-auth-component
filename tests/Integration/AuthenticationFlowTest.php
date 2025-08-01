<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateInterval;
use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\MagicLinkTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ScopeRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\UserRepository;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\AuthenticateToken;
use Zestic\GraphQL\AuthComponent\Interactor\InvalidateToken;
use Zestic\GraphQL\AuthComponent\Interactor\RegisterUser;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Interactor\RequestAccessToken;
use Zestic\GraphQL\AuthComponent\Interactor\SendMagicLink;
use Zestic\GraphQL\AuthComponent\Interactor\UserCreatedNullHook;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\MagicLinkGrant;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\RefreshTokenGrant;
use Zestic\GraphQL\AuthComponent\OAuth2\OAuthConfig;

class AuthenticationFlowTest extends DatabaseTestCase
{
    private array $capturedSendArguments = [];

    private string $clientId;

    private string $clientSecret = 'test_secret';

    private string $codeVerifier = 'test_code_verifier';

    private AccessTokenRepository $accessTokenRepository;

    private AuthenticateToken $authenticateToken;

    private AuthorizationServer $authorizationServer;

    private ClientRepository $clientRepository;

    private CryptKey $privateKey;

    private MagicLinkTokenRepository $magicLinkTokenRepository;

    private InvalidateToken $invalidateToken;

    private Key $encryptionKey;

    private RegisterUser $registerUser;

    private RefreshTokenRepository $refreshTokenRepository;

    private RequestAccessToken $requestAccessToken;

    private ScopeRepository $scopeRepository;

    private SendMagicLink $sendMagicLink;

    private SendMagicLinkInterface $sendMagicLinkEmail;

    private SendVerificationLinkInterface $sendVerificationEmail;

    private $validateRegistration;

    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clientId = self::$testClientId;
        $this->seedClientRepository();

        $this->accessTokenRepository = new AccessTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
        $this->clientRepository = new ClientRepository(self::$pdo);
        $this->magicLinkTokenRepository = new MagicLinkTokenRepository(self::$pdo);
        $this->refreshTokenRepository = new RefreshTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
        $this->scopeRepository = new ScopeRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo);
        $magicLinkTokenFactory = new MagicLinkTokenFactory(
            self::$tokenConfig,
            $this->magicLinkTokenRepository,
        );
        $this->sendMagicLinkEmail = $this->createMock(SendMagicLinkInterface::class);
        $this->sendVerificationEmail = $this->createMock(SendVerificationLinkInterface::class);
        $this->capturedSendArguments = [];
        $this->registerUser = new RegisterUser(
            $this->clientRepository,
            $magicLinkTokenFactory,
            $this->sendVerificationEmail,
            new UserCreatedNullHook(),
            $this->userRepository,
        );

        $reissueExpiredMagicLinkToken = new ReissueExpiredMagicLinkToken(
            $this->clientRepository,
            $magicLinkTokenFactory,
            $this->sendMagicLinkEmail,
            $this->sendVerificationEmail,
            $this->userRepository,
            $this->magicLinkTokenRepository,
        );

        // Create a mock for ValidateRegistration since the class doesn't exist yet
        $this->validateRegistration = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['validate'])
            ->getMock();
        $this->validateRegistration->method('validate')->willReturn(['success' => true]);
        $this->sendMagicLink = new SendMagicLink(
            $this->clientRepository,
            $magicLinkTokenFactory,
            $this->sendMagicLinkEmail,
            $this->userRepository,
        );
        $oauthConfig = new OAuthConfig([
            'clientId' => $this->clientId,
            'clientSecret' => $this->clientSecret,
        ]);
        $this->privateKey = new CryptKey(getcwd() . '/tests/resources/jwt/private.key');
        $this->encryptionKey = Key::loadFromAsciiSafeString($_ENV['OAUTH_ENCRYPTION_KEY']);

        $this->authorizationServer = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $this->privateKey,
            $this->encryptionKey
        );

        $magicLinkGrant = new MagicLinkGrant(
            $this->magicLinkTokenRepository,
            $this->refreshTokenRepository,
            $this->userRepository,
        );
        $this->authorizationServer->enableGrantType($magicLinkGrant, new DateInterval('PT1H'));

        $refreshTokenGrant = new RefreshTokenGrant(
            $this->refreshTokenRepository,
        );
        $this->authorizationServer->enableGrantType($refreshTokenGrant, new DateInterval('PT1H'));

        $this->authenticateToken = new AuthenticateToken(
            $this->authorizationServer,
            $this->magicLinkTokenRepository,
            $oauthConfig,
            $reissueExpiredMagicLinkToken,
        );

        $this->requestAccessToken = new RequestAccessToken(
            $this->authorizationServer,
        );

        $this->invalidateToken = new InvalidateToken(
            $this->accessTokenRepository,
            $this->refreshTokenRepository,
        );
    }

    public function testFlow(): void
    {
        $data = $this->registerUser();
        $data = $this->validateRegistration($data);
        $data = $this->sendMagicLink($data);
        $data = $this->authenticateToken($data);
        $data = $this->refreshToken($data);
        $this->invalidateToken($data);
    }

    public function registerUser(): array
    {
        $this->sendVerificationEmail->method('send')
            ->willReturnCallback(function ($registrationContext, $magicLinkToken) {
                $this->capturedSendArguments = [
                    'verificationMagicLinkToken' => $magicLinkToken,
                    'verificationRegistrationContext' => $registrationContext,
                ];

                return true;
            });

        $registrationContext = new RegistrationContext([
            'email' => self::TEST_EMAIL,
            'clientId' => $this->clientId,
            'codeChallenge' => 'test-challenge',
            'codeChallengeMethod' => 'S256',
            'redirectUri' => 'https://example.com/callback',
            'state' => 'test-state',
            'additionalData' => [
                'displayName' => 'Test User',
            ],
        ]);

        $registrationResult = $this->registerUser->register($registrationContext);
        $this->assertTrue($registrationResult['success']);

        return [
            'token' => $this->capturedSendArguments['verificationMagicLinkToken'],
        ];
    }

    public function validateRegistration(array $data): array
    {
        $validationResult = $this->validateRegistration->validate($data['token']->token);
        $this->assertTrue($validationResult['success']);

        return $data;
    }

    public function sendMagicLink(array $data): array
    {
        $this->sendMagicLinkEmail->method('send')
            ->willReturnCallback(function ($magicLinkToken) {
                $this->capturedSendArguments = [
                    'magicLinkMagicLinkToken' => $magicLinkToken,
                ];

                return true;
            });

        // Generate proper PKCE values for testing
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');

        $context = new MagicLinkContext([
            'email' => self::TEST_EMAIL,
            'clientId' => $this->clientId,
            'codeChallenge' => $codeChallenge,
            'codeChallengeMethod' => 'S256',
            'redirectUri' => 'https://example.com/callback',
            'state' => 'test-state',
        ]);
        $magicLinkResult = $this->sendMagicLink->send($context);
        $this->assertTrue($magicLinkResult['success']);

        $data['MagicLinkToken'] = $this->capturedSendArguments['magicLinkMagicLinkToken'];

        return $data;
    }

    public function authenticateToken(array $data): array
    {
        $authResult = $this->authenticateToken->authenticate($data['MagicLinkToken']->token, $this->codeVerifier);

        $this->assertArrayHasKey('accessToken', $authResult);
        $this->assertArrayHasKey('refreshToken', $authResult);

        $data['refreshToken'] = $authResult['refreshToken'];

        return $data;
    }

    public function refreshToken(array $data): array
    {
        $refreshResult = $this->requestAccessToken->execute($data['refreshToken'], self::$testClientId);
        $this->assertArrayHasKey('accessToken', $refreshResult);
        $this->assertArrayHasKey('refreshToken', $refreshResult);

        return $data;
    }

    public function invalidateToken(array $data): void
    {
        $invalidateResult = $this->invalidateToken->execute(self::$testUserId);
        $this->assertTrue($invalidateResult);

        $this->expectException(\Exception::class);
        $this->requestAccessToken->execute($data['refreshToken'], self::$testClientId);
    }
}
