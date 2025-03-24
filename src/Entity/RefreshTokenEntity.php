<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;
use Zestic\GraphQL\AuthComponent\Entity\Traits\TokenIdentifiersTrait;

class RefreshTokenEntity implements RefreshTokenEntityInterface, TokenIdentifiersInterface
{
    use RefreshTokenTrait;
    use EntityTrait;
    use TokenIdentifiersTrait;

    public function setAccessToken(AccessTokenEntityInterface $accessToken): void
    {
        $this->setClientIdentifier($accessToken->getClient()->getIdentifier());
        $this->setUserIdentifier($accessToken->getUserIdentifier() ?? '');
        $this->accessToken = $accessToken;
    }
}
