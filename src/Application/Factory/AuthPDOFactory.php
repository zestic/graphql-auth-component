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

        if ($driver === 'mysql') {
            $config = [
                'driver' => 'mysql',
                'host' => getenv('AUTH_DB_HOST'),
                'dbname' => getenv('AUTH_DB_NAME'),
                'port' => getenv('AUTH_DB_PORT'),
                'username' => getenv('AUTH_DB_USER'),
                'password' => getenv('AUTH_DB_PASS'),
            ];
        } else {
            $config = [
                'driver' => 'pgsql',
                'host' => getenv('AUTH_PG_HOST'),
                'dbname' => getenv('AUTH_PG_DB_NAME'),
                'port' => getenv('AUTH_PG_PORT'),
                'username' => getenv('AUTH_PG_USER'),
                'password' => getenv('AUTH_PG_PASS'),
            ];
        }

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
        return new AuthPDO($dsn, $config['username'], $config['password']);
    }
}
