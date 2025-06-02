<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\Interactor\InvalidateToken;

class InvalidateTokenTest extends TestCase
{
    private AccessTokenRepository $accessTokenRepository;

    private RefreshTokenRepository $refreshTokenRepository;

    private InvalidateToken $invalidateToken;

    protected function setUp(): void
    {
        $this->accessTokenRepository = $this->createMock(AccessTokenRepository::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepository::class);
        $this->invalidateToken = new InvalidateToken(
            $this->accessTokenRepository,
            $this->refreshTokenRepository
        );
    }

    public function testExecuteSuccessfully(): void
    {
        $userId = 'user_123';
        $accessToken1 = $this->createMock(AccessTokenEntityInterface::class);
        $accessToken2 = $this->createMock(AccessTokenEntityInterface::class);
        $refreshToken1 = $this->createMock(RefreshTokenEntityInterface::class);
        $refreshToken2 = $this->createMock(RefreshTokenEntityInterface::class);

        // Setup access tokens
        $accessToken1->method('getIdentifier')->willReturn('access_token_1');
        $accessToken2->method('getIdentifier')->willReturn('access_token_2');

        // Setup refresh tokens
        $refreshToken1->method('getIdentifier')->willReturn('refresh_token_1');
        $refreshToken2->method('getIdentifier')->willReturn('refresh_token_2');

        // Expect to find access tokens for user
        $this->accessTokenRepository->expects($this->once())
            ->method('findTokensByUserId')
            ->with($userId)
            ->willReturn([$accessToken1, $accessToken2]);

        // Expect to revoke access tokens
        $this->accessTokenRepository->expects($this->exactly(2))
            ->method('revokeAccessToken')
            ->willReturnCallback(function ($tokenId) {
                $this->assertContains($tokenId, ['access_token_1', 'access_token_2']);

                return null;
            });

        // Expect to find refresh tokens
        $this->refreshTokenRepository
            ->method('findRefreshTokensByAccessTokenId')
            ->willReturnMap([
                ['access_token_1', [$refreshToken1]],
                ['access_token_2', [$refreshToken2]],
            ]);

        // Expect to revoke refresh tokens
        $this->refreshTokenRepository->expects($this->exactly(2))
            ->method('revokeRefreshToken')
            ->willReturnCallback(function ($tokenId) {
                $this->assertContains($tokenId, ['refresh_token_1', 'refresh_token_2']);

                return null;
            });

        $result = $this->invalidateToken->execute($userId);
        $this->assertTrue($result);
    }

    public function testExecuteWithNoTokens(): void
    {
        $userId = 'user_with_no_tokens';

        $this->accessTokenRepository->expects($this->once())
            ->method('findTokensByUserId')
            ->with($userId)
            ->willReturn([]);

        // Should not try to revoke any tokens
        $this->accessTokenRepository->expects($this->never())
            ->method('revokeAccessToken');
        $this->refreshTokenRepository->expects($this->never())
            ->method('findRefreshTokensByAccessTokenId');
        $this->refreshTokenRepository->expects($this->never())
            ->method('revokeRefreshToken');

        $result = $this->invalidateToken->execute($userId);
        $this->assertTrue($result);
    }
}
