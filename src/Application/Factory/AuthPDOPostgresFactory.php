<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use RuntimeException;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;

class AuthPDOPostgresFactory
{
    public function __invoke(ContainerInterface $container): AuthPDO
    {
        $host = getenv('AUTH_PG_HOST');
        $name = getenv('AUTH_PG_DB_NAME');
        $port = getenv('AUTH_PG_PORT');
        $user = getenv('AUTH_PG_USER');
        $pass = getenv('AUTH_PG_PASS');
        $schema = getenv('AUTH_PG_SCHEMA');

        if (!is_string($host) || !is_string($name) || !is_string($port) || !is_string($user) || !is_string($pass) || !is_string($schema)) {
            throw new RuntimeException('Missing or invalid PostgreSQL configuration');
        }

        $dsn = sprintf(
            'pgsql:host=%s;dbname=%s;port=%s;options=--search_path=%s',
            $host,
            $name,
            $port,
            $schema
        );

        return new AuthPDO($dsn, $user, $pass);
    }
}
