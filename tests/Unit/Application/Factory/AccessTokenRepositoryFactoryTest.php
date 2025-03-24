<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AccessTokenRepositoryFactory;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class AccessTokenRepositoryFactoryTest extends TestCase
{
    private ContainerInterface&MockObject $container;
    private AccessTokenRepositoryFactory $factory;
    private AuthPDO&MockObject $pdo;
    private TokenConfig&MockObject $tokenConfig;

    protected function setUp(): void
    {
        $this->container = $this->getMockBuilder(ContainerInterface::class)
            ->getMock();
        $this->pdo = $this->getMockBuilder(AuthPDO::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->tokenConfig = $this->getMockBuilder(TokenConfig::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->factory = new AccessTokenRepositoryFactory();
    }

    public function testInvokeWithMySQLConfig(): void
    {
        putenv('AUTH_DB_DRIVER=mysql');
        $this->container->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(fn($id) => match ($id) {
                'auth.mysql.pdo' => $this->pdo,
                TokenConfig::class => $this->tokenConfig,
                default => null,
            });

        $repository = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
    }

    public function testInvokeWithPostgreSQLConfig(): void
    {
        putenv('AUTH_DB_DRIVER=pgsql');
        $this->container->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(fn($id) => match ($id) {
                'auth.postgres.pdo' => $this->pdo,
                TokenConfig::class => $this->tokenConfig,
                default => null,
            });

        $repository = $this->factory->__invoke($this->container);
        $this->assertInstanceOf(AccessTokenRepository::class, $repository);
    }

    protected function tearDown(): void
    {
        putenv('AUTH_DB_DRIVER');
    }
}
