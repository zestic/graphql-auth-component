<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zestic\GraphQL\AuthComponent\Interactor\InvalidateToken;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;

class InvalidateTokenTest extends TestCase
{
    private ResourceServer $resourceServer;
    private AccessTokenRepositoryInterface $accessTokenRepository;
    private RefreshTokenRepositoryInterface $refreshTokenRepository;
    private InvalidateToken $invalidateToken;

    protected function setUp(): void
    {
        $this->resourceServer = $this->createMock(ResourceServer::class);
        $this->accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->invalidateToken = new InvalidateToken(
            $this->resourceServer,
            $this->accessTokenRepository,
            $this->refreshTokenRepository
        );
    }

    public function testExecuteSuccessfully(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $validatedRequest = $this->createMock(ServerRequestInterface::class);
        $accessTokenId = 'access_token_123';

        $validatedRequest->expects($this->once())
            ->method('getAttribute')
            ->willReturn($accessTokenId);

        $this->resourceServer->expects($this->once())
            ->method('validateAuthenticatedRequest')
            ->with($request)
            ->willReturn($validatedRequest);

        $this->accessTokenRepository->expects($this->once())
            ->method('revokeAccessToken')
            ->with($accessTokenId);

        $refreshToken1 = $this->createMock(RefreshTokenEntityInterface::class);
        $refreshToken1->method('getIdentifier')->willReturn('refresh_token_1');
        $refreshToken2 = $this->createMock(RefreshTokenEntityInterface::class);
        $refreshToken2->method('getIdentifier')->willReturn('refresh_token_2');

        $this->refreshTokenRepository->expects($this->once())
            ->method('findRefreshTokensByAccessTokenId')
            ->with($accessTokenId)
            ->willReturn([$refreshToken1, $refreshToken2]);

        $expectedTokens = ['refresh_token_1', 'refresh_token_2'];
        $this->refreshTokenRepository->expects($this->exactly(2))
            ->method('revokeRefreshToken')
            ->willReturnCallback(function ($argument) use (&$expectedTokens) {
                $this->assertEquals(array_shift($expectedTokens), $argument);

                return true;
            });
        $result = $this->invalidateToken->execute($request);
        $this->assertTrue($result);
    }

    public function testExecuteWithOAuthServerException(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $this->resourceServer->expects($this->once())
            ->method('validateAuthenticatedRequest')
            ->with($request)
            ->willThrowException(OAuthServerException::accessDenied('Access token is invalid'));
        $result = $this->invalidateToken->execute($request);
        $this->assertFalse($result);
    }
}
