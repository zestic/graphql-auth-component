<?php

declare(strict_types=1);

namespace Tests\Integration\Factory;

use Tests\Integration\DatabaseTestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOMySQLFactory;

class AuthPDOMySQLFactoryTest extends DatabaseTestCase
{
    private ContainerInterface $container;
    private AuthPDOMySQLFactory $factory;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new AuthPDOMySQLFactory();
    }

    public function testInvokeWithValidConfig(): void
    {
        putenv('AUTH_DB_HOST=localhost');
        putenv('AUTH_DB_NAME=graphql_auth_test');
        putenv('AUTH_DB_PORT=3306');
        putenv('AUTH_DB_USER=test');
        putenv('AUTH_DB_PASS=password1');

        $pdo = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(AuthPDO::class, $pdo);
    }

    public function testInvokeWithMissingConfig(): void
    {
        putenv('AUTH_DB_HOST');
        putenv('AUTH_DB_NAME');
        putenv('AUTH_DB_PORT');
        putenv('AUTH_DB_USER');
        putenv('AUTH_DB_PASS');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing or invalid MySQL configuration');
        $this->factory->__invoke($this->container);
    }

    protected function tearDown(): void
    {
        putenv('AUTH_DB_HOST');
        putenv('AUTH_DB_NAME');
        putenv('AUTH_DB_PORT');
        putenv('AUTH_DB_USER');
        putenv('AUTH_DB_PASS');
    }
}
