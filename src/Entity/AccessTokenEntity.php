<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use Zestic\GraphQL\AuthComponent\Entity\Traits\EntityTrait;

class AccessTokenEntity implements AccessTokenEntityInterface, TokenScopesInterface
{
    use AccessTokenTrait;
    use EntityTrait;
    use TokenEntityTrait;
    use TokenScopesTrait;

    private bool $revoked;

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): void
    {
        $this->revoked = $revoked;
    }

    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function setScopes(array $scopes): void
    {
        foreach ($scopes as $scope) {
            $this->addScope($scope);
        }
    }
}
