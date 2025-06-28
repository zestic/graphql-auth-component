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

    private ?string $clientSecret = null;

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setRedirectUri(string|array $redirectUri): void
    {
        $this->redirectUri = $redirectUri;
    }

    public function setIsConfidential(bool $isConfidential): void
    {
        $this->isConfidential = $isConfidential;
    }

    public function setClientSecret(?string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }
}
