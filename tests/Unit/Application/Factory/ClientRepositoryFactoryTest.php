<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\ClientRepositoryFactory;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;

class ClientRepositoryFactoryTest extends TestCase
{
    private ContainerInterface&MockObject $container;
    private ClientRepositoryFactory $factory;
    private AuthPDO&MockObject $pdo;

    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $this->pdo = $this->getMockBuilder(AuthPDO::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new ClientRepositoryFactory();
    }

    public function testInvokeWithMySQLConfig(): void
    {
        putenv('AUTH_DB_DRIVER=mysql');
        $this->container->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($id) => match ($id) {
                'auth.mysql.pdo' => $this->pdo,
                default => null,
            });

        $repository = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(ClientRepository::class, $repository);
    }

    public function testInvokeWithPostgreSQLConfig(): void
    {
        putenv('AUTH_DB_DRIVER=pgsql');
        $this->container->expects($this->once())
            ->method('get')
            ->willReturnCallback(fn($id) => match ($id) {
                'auth.postgres.pdo' => $this->pdo,
                default => null,
            });

        $repository = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(ClientRepository::class, $repository);
    }

    protected function tearDown(): void
    {
        putenv('AUTH_DB_DRIVER');
    }
}
