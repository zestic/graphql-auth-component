<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent;

use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\TokenConfigFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\UserRepositoryFactory;
use Zestic\GraphQL\AuthComponent\DB\MySQL\UserRepository;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    private function getDependencies(): array
    {
        return [
            'factories' => [
                AuthPDO::class => AuthPDOFactory::class,
                TokenConfig::class => TokenConfigFactory::class,
                UserRepository::class => UserRepositoryFactory::class,
                UserRepositoryInterface::class => UserRepositoryFactory::class,
            ],
        ];
    }
}
