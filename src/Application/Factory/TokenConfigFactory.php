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
            $tokenConfig['access_token_ttl'] ?? 60,
            $tokenConfig['login_ttl'] ?? 10,
            $tokenConfig['refresh_token_ttl'] ?? 10080,
            $tokenConfig['registration_ttl'] ?? 1440,
        );
    }
}
