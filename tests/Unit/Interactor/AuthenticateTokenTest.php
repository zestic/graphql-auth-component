<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Interactor\AuthenticateToken;
use Zestic\GraphQL\AuthComponent\OAuth2\OAuthConfig;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class AuthenticateTokenTest extends TestCase
{
    private AuthorizationServer $authorizationServer;
    private EmailTokenRepositoryInterface $emailTokenRepository;
    private OAuthConfig $oauthConfig;
    private AuthenticateToken $authenticateToken;

    protected function setUp(): void
    {
        $this->authorizationServer = $this->createMock(AuthorizationServer::class);
        $this->emailTokenRepository = $this->createMock(EmailTokenRepositoryInterface::class);
        $this->oauthConfig = $this->createMock(OAuthConfig::class);

        $this->authenticateToken = new AuthenticateToken(
            $this->authorizationServer,
            $this->emailTokenRepository,
            $this->oauthConfig
        );
    }

    public function testAuthenticateWithValidToken(): void
    {
        $token = 'valid_token';
        $emailToken = $this->createMock(EmailToken::class);
        $emailToken->method('isExpired')->willReturn(false);

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $this->oauthConfig->method('getClientId')->willReturn('client_id');
        $this->oauthConfig->method('getClientSecret')->willReturn('client_secret');

        $expiresAt = (new \DateTime('+ 1 hour'))->getTimestamp();
        $response = new Response();
        $response->getBody()->write(json_encode([
            'access_token' => 'new_access_token',
            'refresh_token' => 'new_refresh_token',
            'expires_at' => $expiresAt,
        ]));

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($response);

        $this->emailTokenRepository->expects($this->once())
            ->method('delete')
            ->with($token);

        $result = $this->authenticateToken->authenticate($token);

        $this->assertSame('new_access_token', $result['accessToken']);
        $this->assertSame('new_refresh_token', $result['refreshToken']);
        $this->assertSame($expiresAt, $result['expiresAt']);
    }

    public function testAuthenticateWithExpiredToken(): void
    {
        $token = 'expired_token';
        $emailToken = $this->createMock(EmailToken::class);
        $emailToken->method('isExpired')->willReturn(true);

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->authenticateToken->authenticate($token);
    }

    public function testAuthenticateWithNonExistentToken(): void
    {
        $token = 'non_existent_token';

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn(null);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired token');

        $this->authenticateToken->authenticate($token);
    }

    public function testAuthenticateWithOAuthServerException(): void
    {
        $token = 'valid_token';
        $emailToken = $this->createMock(EmailToken::class);
        $emailToken->method('isExpired')->willReturn(false);

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willThrowException(new OAuthServerException('OAuth error', 0, 'oauth_error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Authentication failed: OAuth error');

        $this->authenticateToken->authenticate($token);
    }
}
