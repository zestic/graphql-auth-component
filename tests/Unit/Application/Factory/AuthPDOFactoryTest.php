<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOFactory;

class AuthPDOFactoryTest extends TestCase
{
    private ContainerInterface $container;
    private AuthPDOFactory $factory;
    private AuthPDO&MockObject $pdo;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = $this->getMockBuilder(AuthPDOFactory::class)
            ->onlyMethods(['buildPDO'])
            ->getMock();
        $this->pdo = $this->getMockBuilder(AuthPDO::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    public function testInvokeWithMySQLConfig(): void
    {
        putenv('AUTH_DB_DRIVER=mysql');
        putenv('AUTH_DB_HOST=localhost');
        putenv('AUTH_DB_NAME=test');
        putenv('AUTH_DB_PORT=3306');
        putenv('AUTH_DB_USER=test');
        putenv('AUTH_DB_PASS=test');

        $this->factory->expects($this->once())
            ->method('buildPDO')
            ->willReturn($this->pdo);

        $result = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(AuthPDO::class, $result);
    }

    public function testInvokeWithPostgreSQLConfig(): void
    {
        putenv('AUTH_DB_DRIVER=pgsql');
        putenv('AUTH_DB_HOST=localhost');
        putenv('AUTH_DB_NAME=test');
        putenv('AUTH_DB_PORT=5432');
        putenv('AUTH_DB_USER=test');
        putenv('AUTH_DB_PASS=test');

        $this->factory->expects($this->once())
            ->method('buildPDO')
            ->willReturn($this->pdo);

        $result = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(AuthPDO::class, $result);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('AUTH_DB_DRIVER');
        putenv('AUTH_DB_HOST');
        putenv('AUTH_DB_NAME');
        putenv('AUTH_DB_PORT');
        putenv('AUTH_DB_USER');
        putenv('AUTH_DB_PASS');
        putenv('AUTH_DB_SCHEMA');
    }
}
