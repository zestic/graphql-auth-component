<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent;

use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AccessTokenRepositoryFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOMySQLFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOPostgresFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\ClientRepositoryFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\EmailTokenRepositoryFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\RefreshTokenRepositoryFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\TokenConfigFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\UserRepositoryFactory;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\EmailTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\UserRepository;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;
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
                'auth.mysql.pdo' => AuthPDOMySQLFactory::class,
                'auth.postgres.pdo' => AuthPDOPostgresFactory::class,
                AccessTokenRepository::class => AccessTokenRepositoryFactory::class,
                ClientRepository::class => ClientRepositoryFactory::class,
                EmailTokenRepository::class => EmailTokenRepositoryFactory::class,
                EmailTokenRepositoryInterface::class => EmailTokenRepositoryFactory::class,
                RefreshTokenRepository::class => RefreshTokenRepositoryFactory::class,
                TokenConfig::class => TokenConfigFactory::class,
                UserRepository::class => UserRepositoryFactory::class,
                UserRepositoryInterface::class => UserRepositoryFactory::class,
            ],
        ];
    }
}
