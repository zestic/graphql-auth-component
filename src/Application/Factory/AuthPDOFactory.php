<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;

class AuthPDOFactory
{
    public function __invoke(ContainerInterface $container): AuthPDO
    {
        $driver = getenv('AUTH_DB_DRIVER') ?: 'mysql';

        $config = [
            'driver' => $driver,
            'host' => getenv('AUTH_DB_HOST'),
            'dbname' => getenv('AUTH_DB_NAME'),
            'port' => getenv('AUTH_DB_PORT'),
            'username' => getenv('AUTH_DB_USER'),
            'password' => getenv('AUTH_DB_PASS'),
        ];

        $this->validateConfig($config);
        return $this->buildPDO($config);
    }

    private function validateConfig(array $config): void
    {
        $required = ['driver', 'host', 'dbname', 'port', 'username', 'password'];
        foreach ($required as $key) {
            if (!isset($config[$key]) || !is_string($config[$key])) {
                throw new \RuntimeException("Missing or invalid database configuration: {$key}");
            }
        }
    }

    protected function buildPDO(array $config): AuthPDO
    {
        $dsn = sprintf(
            '%s:host=%s;port=%s;dbname=%s',
            $config['driver'],
            $config['host'],
            $config['port'],
            $config['dbname']
        );
        $pdo = new AuthPDO($dsn, $config['username'], $config['password']);
        
        if ($config['driver'] === 'pgsql') {
            $schema = getenv('AUTH_DB_SCHEMA') ?: 'graphql_auth_test';
            $pdo->exec("SET search_path TO {$schema}");
        }
        
        return $pdo;
    }
}
