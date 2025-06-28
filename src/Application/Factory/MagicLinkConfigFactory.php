<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkConfig;

class MagicLinkConfigFactory
{
    public function __invoke(ContainerInterface $container): MagicLinkConfig
    {
        $config = $container->get('config');
        $magicLinkConfig = $config['auth']['magicLink'] ?? [];

        return new MagicLinkConfig(
            webAppUrl: $magicLinkConfig['webAppUrl'],
            authCallbackPath: $magicLinkConfig['authCallbackPath'],
            magicLinkPath: $magicLinkConfig['magicLinkPath'],
            defaultSuccessMessage: $magicLinkConfig['defaultSuccessMessage'],
            registrationSuccessMessage: $magicLinkConfig['registrationSuccessMessage'],
        );
    }
}
