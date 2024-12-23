<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\Exception\OAuthServerException;
use Zestic\GraphQL\AuthComponent\DB\MySQL\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\MySQL\RefreshTokenRepository;

class InvalidateToken
{
    public function __construct(
        private AccessTokenRepository $accessTokenRepository,
        private RefreshTokenRepository $refreshTokenRepository
    ) {
    }

    public function execute(string $userId): bool
    {
        try {
            $accessTokens = $this->accessTokenRepository->findTokensByUserId($userId);
            foreach ($accessTokens as $accessToken) {
                $this->accessTokenRepository->revokeAccessToken($accessToken->getIdentifier());

                // Find and revoke all refresh tokens for this access token
                $refreshTokens = $this->refreshTokenRepository->findRefreshTokensByAccessTokenId($accessToken->getIdentifier());
                foreach ($refreshTokens as $refreshToken) {
                    $this->refreshTokenRepository->revokeRefreshToken($refreshToken->getIdentifier());
                }
            }

            return true;
        } catch (OAuthServerException $exception) {
            // Handle or log the exception as needed
            return false;
        }
    }
}
