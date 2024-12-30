<?php

declare(strict_types=1);

namespace Tests\Integration;

use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkEmailInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationEmailInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\DB\MySQL\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\ClientRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\EmailTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\ScopeRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\UserRepository;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\AuthenticateToken;
use Zestic\GraphQL\AuthComponent\Interactor\InvalidateToken;
use Zestic\GraphQL\AuthComponent\Interactor\RegisterUser;
use Zestic\GraphQL\AuthComponent\Interactor\RequestAccessToken;
use Zestic\GraphQL\AuthComponent\Interactor\SendMagicLink;
use Zestic\GraphQL\AuthComponent\Interactor\ValidateRegistration;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\MagicLinkGrant;
use Zestic\GraphQL\AuthComponent\OAuth2\OAuthConfig;

class AuthenticationFlowTest extends DatabaseTestCase
{
    private array $capturedSendArguments = [];
    private string $clientId = 'test_client';
    private string $clientSecret = 'test_secret';
    private AccessTokenRepository $accessTokenRepository;
    private AuthenticateToken $authenticateToken;
    private AuthorizationServer $authorizationServer;
    private ClientRepository $clientRepository;
    private CryptKey $privateKey;
    private EmailTokenRepository $emailTokenRepository;
    private InvalidateToken $invalidateToken;
    private Key $encryptionKey;
    private RegisterUser $registerUser;
    private RefreshTokenRepository $refreshTokenRepository;
    private RequestAccessToken $requestAccessToken;
    private ScopeRepository $scopeRepository;
    private SendMagicLink $sendMagicLink;
    private SendMagicLinkEmailInterface $sendMagicLinkEmail;
    private SendVerificationEmailInterface $sendVerificationEmail;
    private ValidateRegistration $validateRegistration;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedClientRepository();

        $this->accessTokenRepository = new AccessTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
        $this->clientRepository = new ClientRepository(self::$pdo);
        $this->emailTokenRepository = new EmailTokenRepository(self::$pdo);
        $this->refreshTokenRepository = new RefreshTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
        $this->scopeRepository = new ScopeRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo);
        $emailTokenFactory = new EmailTokenFactory(
            self::$tokenConfig,
            $this->emailTokenRepository,
        );
        $this->sendMagicLinkEmail = $this->createMock(SendMagicLinkEmailInterface::class);
        $this->sendVerificationEmail = $this->createMock(SendVerificationEmailInterface::class);
        $this->capturedSendArguments = [];
        $this->registerUser = new RegisterUser(
            $emailTokenFactory,
            $this->sendVerificationEmail,
            $this->userRepository,
        );
        $this->validateRegistration = new ValidateRegistration(
            $this->emailTokenRepository,
            $this->userRepository,
        );
        $this->sendMagicLink = new SendMagicLink(
            $emailTokenFactory,
            $this->sendMagicLinkEmail,
            $this->userRepository,
        );
        $oauthConfig = new OAuthConfig([
            'clientId'     => $this->clientId,
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
            $this->emailTokenRepository,
            $this->refreshTokenRepository,
            $this->userRepository,
        );
        $this->authorizationServer->enableGrantType($magicLinkGrant);

        $refreshTokenGrant = new RefreshTokenGrant(
            $this->refreshTokenRepository,
        );
        $this->authorizationServer->enableGrantType($refreshTokenGrant);
        
        $this->authenticateToken = new AuthenticateToken(
            $this->authorizationServer,
            $this->emailTokenRepository,
            $oauthConfig,
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
            ->willReturnCallback(function ($registrationContext, $emailToken) {
                $this->capturedSendArguments = [
                    'verificationEmailToken' => $emailToken,
                    'verificationRegistrationContext' => $registrationContext,
                ];
                return true;
            });

        $registrationContext = new RegistrationContext(
            self::TEST_EMAIL, // Use the same email as theestUserEmail,
            [
                'displayName' => 'Test User',
            ],
        );
        
        $registrationResult = $this->registerUser->register($registrationContext);
        $this->assertTrue($registrationResult['success']);

        return [
            'token' => $this->capturedSendArguments['verificationEmailToken'],
        ];
    }

    public function validateRegistration(array $data): array
    {
        $validationResult = $this->validateRegistration->validate($data['token']->token);
        $this->assertTrue($validationResult);

        return $data;
    }

    public function sendMagicLink(array $data): array
    {
        $this->sendMagicLinkEmail->method('send')
            ->willReturnCallback(function ($emailToken) {
                $this->capturedSendArguments = [
                    'magicLinkEmailToken' => $emailToken,
                ];
                return true;
            });

        $magicLinkResult = $this->sendMagicLink->send(self::TEST_EMAIL);
        $this->assertTrue($magicLinkResult['success']);

        $data['emailToken'] = $this->capturedSendArguments['magicLinkEmailToken'];

        return $data;
    }

    public function authenticateToken(array $data): array
    {
        $authResult = $this->authenticateToken->authenticate($data['emailToken']->token);
        
        $this->assertArrayHasKey('accessToken', $authResult);
        $this->assertArrayHasKey('refreshToken', $authResult);

        $data['refreshToken'] = $authResult['refreshToken'];

        return $data;
    }

    public function refreshToken(array $data): array
    {
        $refreshResult = $this->requestAccessToken->execute($data['refreshToken'], self::TEST_CLIENT_ID);
        $this->assertArrayHasKey('accessToken', $refreshResult);
        $this->assertArrayHasKey('refreshToken', $refreshResult);

        return $data;
    }

    public function invalidateToken(array $data): void
    {
        $invalidateResult = $this->invalidateToken->execute(self::TEST_USER_ID);
        $this->assertTrue($invalidateResult);

        $this->expectException(\Exception::class);
        $this->requestAccessToken->execute($data['refreshToken'], self::TEST_CLIENT_ID);
    }
}
