<?php

declare(strict_types=1);

namespace Tests\Integration;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use Nyholm\Psr7\ServerRequest;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkCommunicationInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationCommunicationInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\DB\MySQL\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\ClientRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\EmailTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\UserRepository;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\AuthenticateToken;
use Zestic\GraphQL\AuthComponent\Interactor\InvalidateToken;
use Zestic\GraphQL\AuthComponent\Interactor\RegisterUser;
use Zestic\GraphQL\AuthComponent\Interactor\SendMagicLink;
use Zestic\GraphQL\AuthComponent\Interactor\ValidateRegistration;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\MagicLinkGrant;
use Zestic\GraphQL\AuthComponent\OAuth2\OAuthConfig;

class AuthenticationFlowTest extends DatabaseTestCase
{
    private array $capturedSendArguments = [];
    private string $clientId = 'test_client';
    private string $clientSecret = 'test_secret';
    private string $testUserEmail = 'test@zestic.com';
    private AccessTokenRepository $accessTokenRepository;
    private AuthenticateToken $authenticateToken;
    private AuthorizationServer $authorizationServer;
    private ClientRepository $clientRepository;
    private EmailTokenRepository $emailTokenRepository;
    private InvalidateToken $invalidateToken;
    private CryptKey $privateKey;
    private RegisterUser $registerUser;
    private SendMagicLink $sendMagicLink;
    private SendMagicLinkCommunicationInterface $sendMagicLinkCommunication;
    private SendVerificationCommunicationInterface $sendVerificationCommunication;
    private ValidateRegistration $validateRegistration;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accessTokenRepository = new AccessTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
        $this->clientRepository = new ClientRepository(self::$pdo);
        $this->emailTokenRepository = new EmailTokenRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo);
        $emailTokenFactory = new EmailTokenFactory(
            self::$tokenConfig,
            $this->emailTokenRepository,
        );
        $this->sendMagicLinkCommunication = $this->createMock(SendMagicLinkCommunicationInterface::class);
        $this->sendVerificationCommunication = $this->createMock(SendVerificationCommunicationInterface::class);
        $this->capturedSendArguments = [];
        $this->registerUser = new RegisterUser(
            $emailTokenFactory,
            $this->sendVerificationCommunication,
            $this->userRepository,
        );
        $this->validateRegistration = new ValidateRegistration(
            $this->emailTokenRepository,
            $this->userRepository,
        );
        $this->sendMagicLink = new SendMagicLink(
            $emailTokenFactory,
            $this->sendMagicLinkCommunication,
            $this->userRepository,
        );
        $oauthConfig = new OAuthConfig([
            'clientId'     => $this->clientId,
            'clientSecret' => $this->clientSecret,
        ]);
        $this->privateKey = new CryptKey(getcwd() . '/tests/resources/jwt/private.key');

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

                $this->authenticateToken = new AuthenticateToken(
                    $this->authorizationServer,
                    $this->emailTokenRepository,
                    $oauthConfig,
                );
                $this->invalidateToken = new InvalidateToken(
                    $this->emailTokenRepository,
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
        $this->sendVerificationCommunication->method('send')
            ->willReturnCallback(function ($registrationContext, $emailToken) {
                $this->capturedSendArguments = [
                    'verificationEmailToken' => $emailToken,
                    'verificationRegistrationContext' => $registrationContext,
                ];
                return true;
            });

        $registrationContext = new RegistrationContext(
            $this->testUserEmail,
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
        $this->sendMagicLinkCommunication->method('send')
            ->willReturnCallback(function ($emailToken) {
                $this->capturedSendArguments = [
                    'magicLinkEmailToken' => $emailToken,
                ];
                return true;
            });

        $magicLinkResult = $this->sendMagicLink->send($this->testUserEmail);
        $this->assertTrue($magicLinkResult['success']);

        $data['emailToken'] = $this->capturedSendArguments['magicLinkEmailToken'];

        return $data;
    }

    public function authenticateToken(array $data): array
    {
        xdebug_break();

        $authResult = $this->authenticateToken->authenticate($data['emailToken']->token);
        $this->assertArrayHasKey('access_token', $authResult);
        $this->assertArrayHasKey('refresh_token', $authResult);

        $data['refreshToken'] = $authResult['refresh_token'];
        return $data;
    }

    public function refreshToken(array $data): array
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withParsedBody([
            'grant_type' => 'refresh_token',
            'refresh_token' => $data['refreshToken'],
            'client_id' => 'test_client',
        ]);
        $refreshResult = $this->authenticateToken->execute($request);
        $this->assertArrayHasKey('access_token', $refreshResult);

        $data['request'] = $request;
        return $data;
    }

    public function invalidateToken(array $data): void
    {
        $invalidateResult = $this->invalidateToken->execute($data['userId']);
        $this->assertTrue($invalidateResult);

        $this->expectException(\Exception::class);
        $this->authenticateToken->execute($data['request']);
    }

    protected function tearDown(): void
    {
        // Clean up the database
//        self::$pdo->exec('TRUNCATE TABLE users');
//        self::$pdo->exec('TRUNCATE TABLE email_tokens');
//        self::$pdo->exec('TRUNCATE TABLE oauth_clients');
        parent::tearDown();
    }
}
