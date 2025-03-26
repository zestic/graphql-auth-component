<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use Zestic\GraphQL\AuthComponent\Entity\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use Zestic\GraphQL\AuthComponent\Entity\Traits\TokenIdentifiersTrait;

class RefreshTokenEntity implements RefreshTokenEntityInterface, TokenIdentifiersInterface
{
    use RefreshTokenTrait;
    use EntityTrait;
    use TokenIdentifiersTrait;


    private bool $revoked = false;

    public function setAccessToken(AccessTokenEntityInterface $accessToken): void
    {
        $this->setClientIdentifier($accessToken->getClient()->getIdentifier());
        $this->setUserIdentifier($accessToken->getUserIdentifier() ?? '');
        $this->accessToken = $accessToken;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): void
    {
        $this->revoked = $revoked;
    }
}
