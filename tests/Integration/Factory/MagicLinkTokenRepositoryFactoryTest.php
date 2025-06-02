<?php

declare(strict_types=1);

namespace Tests\Integration\Factory;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\Application\Factory\MagicLinkTokenRepositoryFactory;
use Zestic\GraphQL\AuthComponent\DB\PDO\MagicLinkTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

class MagicLinkTokenRepositoryFactoryTest extends DatabaseTestCase
{
    public function testInvoke(): void
    {
        // Create the factory
        $factory = new MagicLinkTokenRepositoryFactory();

        // Create a container with AuthPDO
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(self::$driver === 'mysql' ? 'auth.mysql.pdo' : 'auth.postgres.pdo')
            ->willReturn(self::$pdo);

        // Create the repository
        $repository = $factory($container);

        // Assert it's the correct type
        $this->assertInstanceOf(MagicLinkTokenRepository::class, $repository);
        $this->assertInstanceOf(MagicLinkTokenRepositoryInterface::class, $repository);

        // Test that it works by creating and retrieving an email token
        $magicLinkToken = new MagicLinkToken(
            new \DateTimeImmutable('+1 hour'),
            'test_token',
            MagicLinkTokenType::LOGIN,
            self::$testUserId
        );

        // Create the token
        $result = $repository->create($magicLinkToken);
        $this->assertTrue($result);

        // Retrieve the token
        $retrievedToken = $repository->findByToken('test_token');
        $this->assertNotNull($retrievedToken);
        $this->assertEquals('test_token', $retrievedToken->token);
        $this->assertEquals(MagicLinkTokenType::LOGIN, $retrievedToken->tokenType);
        $this->assertEquals(self::$testUserId, $retrievedToken->userId);

        // Clean up
        $repository->delete($retrievedToken);
    }
}
