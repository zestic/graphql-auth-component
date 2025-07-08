<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handler;

use Carbon\CarbonImmutable;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Application\Handler\MagicLinkVerificationHandler;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkConfig;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class MagicLinkVerificationHandlerTest extends TestCase
{
    private MagicLinkVerificationHandler $handler;
    private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository;
    private UserRepositoryInterface $userRepository;
    private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken;
    private MagicLinkConfig $config;
    private AuthorizationServer $authorizationServer;
    private AuthCodeRepositoryInterface $authCodeRepository;
    private ClientRepositoryInterface $clientRepository;
    private ScopeRepositoryInterface $scopeRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->reissueExpiredMagicLinkToken = $this->createMock(ReissueExpiredMagicLinkToken::class);
        $this->authorizationServer = $this->createMock(AuthorizationServer::class);
        $this->authCodeRepository = $this->createMock(AuthCodeRepositoryInterface::class);
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->scopeRepository = $this->createMock(ScopeRepositoryInterface::class);

        $this->config = new MagicLinkConfig(
            webAppUrl: 'https://testapp.com',
            authCallbackPath: '/auth/callback',
            magicLinkPath: '/auth/magic-link',
            defaultSuccessMessage: 'Authentication successful',
            registrationSuccessMessage: 'Registration verified successfully',
        );

        $this->handler = new MagicLinkVerificationHandler(
            $this->magicLinkTokenRepository,
            $this->userRepository,
            $this->reissueExpiredMagicLinkToken,
            $this->config,
            $this->authorizationServer,
            $this->authCodeRepository,
            $this->clientRepository,
            $this->scopeRepository,
        );
    }

    public function testHandleRegistrationVerification(): void
    {
        $token = 'test-registration-token';
        $request = new ServerRequest('GET', '/magic-link/verify?token=' . $token);

        // Create a registration token
        $magicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: '/auth/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::REGISTRATION,
            userId: 'user-123',
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;

        // Create an unverified user (verifiedAt defaults to null)
        $user = new User([], 'Test User', 'test@example.com', 'user-123');

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user-123')
            ->willReturn($user);

        $this->userRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($updatedUser) {
                return $updatedUser->getVerifiedAt() !== null;
            }));

        $response = $this->handler->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('/auth/callback', $location);
        $this->assertStringContainsString('token=' . $token, $location);
        $this->assertStringContainsString('Registration+verified+successfully', $location);
    }

    public function testHandleLoginMagicLinkWithPkce(): void
    {
        $token = 'test-login-token';
        $request = new ServerRequest('GET', '/magic-link/verify?token=' . $token);

        $pkceData = [
            'client_id' => 'mobile-app',
            'redirect_uri' => 'myapp://auth/callback',
            'code_challenge' => 'test-challenge',
            'state' => 'test-state',
        ];

        // Create a login token with PKCE data
        $magicLinkToken = new MagicLinkToken(
            clientId: 'mobile-app',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'myapp://auth/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user-123',
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $response = $this->handler->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('myapp://auth/callback', $location);
        $this->assertStringContainsString('token=' . $token, $location);
        $this->assertStringContainsString('state=test-state', $location);
    }

    public function testHandleTraditionalLoginMagicLink(): void
    {
        $token = 'test-traditional-token';
        $request = new ServerRequest('GET', '/magic-link/verify?token=' . $token);

        // Create a traditional login token (no PKCE data)
        $magicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: '/auth/magic-link',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user-123',
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $response = $this->handler->handle($request);

        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('/auth/magic-link', $location);
        $this->assertStringContainsString('token=' . $token, $location);
    }

    public function testHandleExpiredTokenReissue(): void
    {
        $token = 'expired-token';
        $request = new ServerRequest('GET', '/magic-link/verify?token=' . $token);

        $expiredToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('-1 hour'), // Expired
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user-123',
        );

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn(null);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($expiredToken);

        $this->reissueExpiredMagicLinkToken->expects($this->once())
            ->method('reissue')
            ->with($expiredToken)
            ->willReturn([
                'success' => true,
                'message' => 'New magic link sent',
            ]);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('A new magic link has been sent', (string) $response->getBody());
    }

    public function testHandleInvalidToken(): void
    {
        $token = 'invalid-token';
        $request = new ServerRequest('GET', '/magic-link/verify?token=' . $token);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn(null);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn(null);

        $response = $this->handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Invalid or expired magic link', (string) $response->getBody());
    }

    public function testHandleMissingToken(): void
    {
        $request = new ServerRequest('GET', '/magic-link/verify');

        $response = $this->handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString('Missing token parameter', (string) $response->getBody());
    }
}
