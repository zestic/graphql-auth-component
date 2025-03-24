<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class AccessTokenRepositoryFactory
{
    public function __invoke(ContainerInterface $container): AccessTokenRepository
    {
        $driver = getenv('AUTH_DB_DRIVER') ?: 'mysql';
        $pdoService = $driver === 'mysql' ? 'auth.mysql.pdo' : 'auth.postgres.pdo';

        return new AccessTokenRepository(
            $container->get($pdoService),
            $container->get(TokenConfig::class)
        );
    }
}
