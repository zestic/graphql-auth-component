<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;

class ClientRepositoryFactory
{
    public function __invoke(ContainerInterface $container): ClientRepository
    {
        $driver = getenv('AUTH_DB_DRIVER') ?: 'mysql';
        $pdoService = $driver === 'mysql' ? 'auth.mysql.pdo' : 'auth.postgres.pdo';

        return new ClientRepository(
            $container->get($pdoService)
        );
    }
}
