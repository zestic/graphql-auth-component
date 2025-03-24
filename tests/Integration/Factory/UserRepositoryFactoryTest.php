<?php

declare(strict_types=1);

namespace Tests\Integration\Factory;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\UserRepositoryFactory;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\DB\PDO\UserRepository;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class UserRepositoryFactoryTest extends DatabaseTestCase
{
    public function testInvoke(): void
    {
        // Create the factory
        $factory = new UserRepositoryFactory();

        // Create a container with AuthPDO
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('auth.mysql.pdo')
            ->willReturn(self::$pdo);

        // Create the repository
        $repository = $factory($container);

        // Assert it's the correct type
        $this->assertInstanceOf(UserRepository::class, $repository);
        $this->assertInstanceOf(UserRepositoryInterface::class, $repository);

        // Test that it works by creating and retrieving a user
        $context = new RegistrationContext(
            'test.user@example.com',
            [
                'displayName' => 'Test User',
                'test'        => 'data',
            ],
        );

        $userId = $repository->create($context);
        $this->assertNotEmpty($userId);

        $user = $repository->findUserById($userId);
        $this->assertNotNull($user);
        $this->assertEquals('test.user@example.com', $user->getEmail());
        $this->assertEquals('Test User', $user->displayName);
    }
}
