<?php

declare(strict_types=1);

namespace Tests\Integration\Factory;

use Tests\Integration\DatabaseTestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOPostgresFactory;

class AuthPDOPostgresFactoryTest extends DatabaseTestCase
{
    private ContainerInterface $container;
    private AuthPDOPostgresFactory $factory;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new AuthPDOPostgresFactory();
    }

    public function testInvokeWithValidConfig(): void
    {
        putenv('AUTH_PG_HOST=localhost');
        putenv('AUTH_PG_DB_NAME=test');
        putenv('AUTH_PG_SCHEMA=graphql_auth_test');
        putenv('AUTH_PG_PORT=5432');
        putenv('AUTH_PG_USER=test');
        putenv('AUTH_PG_PASS=password1');

        $pdo = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(AuthPDO::class, $pdo);
    }

    public function testInvokeWithMissingConfig(): void
    {
        putenv('AUTH_PG_HOST');
        putenv('AUTH_PG_DB_NAME');
        putenv('AUTH_PG_PORT');
        putenv('AUTH_PG_USER');
        putenv('AUTH_PG_PASS');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or invalid PostgreSQL configuration');
        $this->factory->__invoke($this->container);
    }

    protected function tearDown(): void
    {
        putenv('AUTH_PG_HOST');
        putenv('AUTH_PG_DB_NAME');
        putenv('AUTH_PG_PORT');
        putenv('AUTH_PG_USER');
        putenv('AUTH_PG_PASS');
    }
}
