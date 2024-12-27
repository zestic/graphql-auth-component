<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;

class AuthPDOFactory
{
    public function __invoke(ContainerInterface $container): AuthPDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;port=%s',
            getenv('AUTH_DB_HOST'),
            getenv('AUTH_DB_NAME'),
            getenv('AUTH_DB_PORT')
        );

        return new AuthPDO(
            $dsn,
            getenv('AUTH_DB_USER'),
            getenv('AUTH_DB_PASS')
        );
    }
}
