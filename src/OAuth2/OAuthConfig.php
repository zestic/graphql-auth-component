<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\OAuth2;

class OAuthConfig
{
    public function __construct(
        private array $config,
    ) {
    }

    public function getClientId(): string
    {
        return $this->config['clientId'];
    }

    public function getClientSecret(): string
    {
        return $this->config['clientSecret'];
    }
}
