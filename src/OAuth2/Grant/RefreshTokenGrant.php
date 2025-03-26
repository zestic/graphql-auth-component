<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\OAuth2\Grant;

use League\OAuth2\Server\Grant\RefreshTokenGrant as BaseRefreshTokenGrant;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;

class RefreshTokenGrant extends BaseRefreshTokenGrant
{
    public function __construct(
        private RefreshTokenRepositoryInterface $refreshTokenRepo,
    ) {
        parent::__construct($refreshTokenRepo);
    }

    /**
     * @return non-empty-string
     */
    protected function generateUniqueIdentifier(int $length = 40): string
    {
        /** @var non-empty-string */
        return $this->refreshTokenRepo->generateUniqueIdentifier($length);
    }
}
