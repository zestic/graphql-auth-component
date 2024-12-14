<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;

class InvalidateToken
{
    public function __construct(
        private ResourceServer $resourceServer,
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository
    ) {
    }

    public function execute(ServerRequestInterface $request): bool
    {
        try {
            $validatedRequest = $this->resourceServer->validateAuthenticatedRequest($request);

            $accessTokenId = $validatedRequest->getAttribute('oauth_access_token_id');

            // Revoke the access token
            $this->accessTokenRepository->revokeAccessToken($accessTokenId);

            // Find and revoke all refresh tokens for this access token
            $refreshTokens = $this->refreshTokenRepository->findRefreshTokensByAccessTokenId($accessTokenId);
            foreach ($refreshTokens as $refreshToken) {
                $this->refreshTokenRepository->revokeRefreshToken($refreshToken->getIdentifier());
            }

            return true;
        } catch (OAuthServerException $exception) {
            // Handle or log the exception as needed
            return false;
        }
    }
}
