<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\DB\PDO\UserRepository;

class UserRepositoryFactory
{
    public function __invoke(ContainerInterface $container): UserRepository
    {
        $driver = getenv('AUTH_DB_DRIVER') ?: 'mysql';
        $pdoService = $driver === 'mysql' ? 'auth.mysql.pdo' : 'auth.postgres.pdo';

        return new UserRepository(
            $container->get($pdoService)
        );
    }
}
