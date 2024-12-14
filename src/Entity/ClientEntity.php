<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ClientEntity implements ClientEntityInterface
{
    use EntityTrait;
    use ClientTrait;

    public function __construct(
        protected string $identifier,
        protected string $name,
        protected string|array $redirectUri,
        bool $isConfidential = false,
    ) {
        $this->isConfidential = $isConfidential;
    }
}
