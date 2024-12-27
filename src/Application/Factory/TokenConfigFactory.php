<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class TokenConfigFactory
{
    public function __invoke(ContainerInterface $container): TokenConfig
    {
        $config = $container->get('config');
        $tokenConfig = $config['auth']['token'] ?? [];

        return new TokenConfig(
            $tokenConfig['access_token_ttl'] ?? 60,      // Default 1 hour
            $tokenConfig['login_ttl'] ?? 10,             // Default 10 minutes
            $tokenConfig['refresh_token_ttl'] ?? 10080,  // Default 1 week
            $tokenConfig['registration_ttl'] ?? 1440,    // Default 24 hours
        );
    }
}
