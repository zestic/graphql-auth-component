<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\DB\PDO\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class RefreshTokenRepositoryFactory
{
    public function __invoke(ContainerInterface $container): RefreshTokenRepository
    {
        $driver = getenv('AUTH_DB_DRIVER') ?: 'mysql';
        $pdoService = $driver === 'mysql' ? 'auth.mysql.pdo' : 'auth.postgres.pdo';

        return new RefreshTokenRepository(
            $container->get($pdoService),
            $container->get(TokenConfig::class)
        );
    }
}
