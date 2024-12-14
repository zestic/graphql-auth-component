<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Repository;

use \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface as OAuthRefreshTokenRepositoryInterface;

interface RefreshTokenRepositoryInterface extends OAuthRefreshTokenRepositoryInterface
{
    public function findRefreshTokensByAccessTokenId(string $accessTokenId): array;
}
