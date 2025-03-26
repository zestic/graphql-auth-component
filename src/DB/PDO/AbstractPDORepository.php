<?php

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use \PDO;

abstract class AbstractPDORepository
{
    protected \PDO $pdo;
    protected readonly bool $isPgsql;
    protected string $schema;

    public function __construct(
        \PDO $pdo,
    ) {
        $this->pdo = $pdo;
        $this->isPgsql = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'pgsql';
        $this->schema = $this->isPgsql ? 'graphql_auth_test.' : '';
    }

    /**
     * @param positive-int $length
     */
    public function generateUniqueIdentifier(int $length = 40): string
    {
        if ($this->isPgsql) {
            return \Ramsey\Uuid\Uuid::uuid4()->toString();
        }
        return bin2hex(random_bytes(max(1, $length)));
    }

    public function isPgsql(): bool
    {
        return $this->isPgsql;
    }

    protected function dbBool(bool $value): string
    {
        return $this->isPgsql ? ($value ? 'true' : 'false') : ($value ? '1' : '0');
    }

    protected function dbNow(): string
    {
        return 'NOW()';
    }

    protected function dbInterval(string $interval): string
    {
        if ($this->isPgsql) {
            return "INTERVAL '$interval'";
        }
        // Convert PostgreSQL interval syntax to MySQL
        $interval = strtolower($interval);
        if (str_contains($interval, 'hour')) {
            return str_replace(' hour', ' HOUR', $interval);
        }
        return $interval;
    }
}