<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Repository;

use \League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface as BaseRefreshTokenRepositoryInterface;

interface RefreshTokenRepositoryInterface extends BaseRefreshTokenRepositoryInterface
{
    public function findRefreshTokensByAccessTokenId(string $accessTokenId): array;

    public function generateUniqueIdentifier(int $length = 40): string;
}
