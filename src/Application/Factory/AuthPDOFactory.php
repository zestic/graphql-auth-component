<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;

class AuthPDOFactory
{
    public function __invoke(ContainerInterface $container): AuthPDO
    {
        $host = getenv('AUTH_DB_HOST');
        $name = getenv('AUTH_DB_NAME');
        $port = getenv('AUTH_DB_PORT');
        $user = getenv('AUTH_DB_USER');
        $pass = getenv('AUTH_DB_PASS');

        if (!is_string($host) || !is_string($name) || !is_string($port) || !is_string($user) || !is_string($pass)) {
            throw new \RuntimeException('Missing or invalid database configuration');
        }

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%s',
            $host,
            $name,
            $port
        );

        return new AuthPDO(
            $dsn,
            $user,
            $pass
        );
    }
}
