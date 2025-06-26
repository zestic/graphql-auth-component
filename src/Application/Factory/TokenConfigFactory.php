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
            $tokenConfig['accessTokenTtl'],
            $tokenConfig['loginTtl'],
            $tokenConfig['refreshTokenTtl'],
            $tokenConfig['registrationTtl'],
        );
    }
}
