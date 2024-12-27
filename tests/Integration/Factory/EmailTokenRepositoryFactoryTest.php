<?php

declare(strict_types=1);

namespace Tests\Integration\Factory;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\EmailTokenRepositoryFactory;
use Zestic\GraphQL\AuthComponent\DB\MySQL\EmailTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class EmailTokenRepositoryFactoryTest extends DatabaseTestCase
{
    public function testInvoke(): void
    {
        // Create the factory
        $factory = new EmailTokenRepositoryFactory();

        // Create a container with AuthPDO
        $container = $this->createMock(\Psr\Container\ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(AuthPDO::class)
            ->willReturn(self::$pdo);

        // Create the repository
        $repository = $factory($container);

        // Assert it's the correct type
        $this->assertInstanceOf(EmailTokenRepository::class, $repository);
        $this->assertInstanceOf(EmailTokenRepositoryInterface::class, $repository);

        // Test that it works by creating and retrieving an email token
        $emailToken = new EmailToken(
            new \DateTimeImmutable('+1 hour'),
            'test_token',
            EmailTokenType::LOGIN,
            self::TEST_USER_ID
        );

        // Create the token
        $result = $repository->create($emailToken);
        $this->assertTrue($result);

        // Retrieve the token
        $retrievedToken = $repository->findByToken('test_token');
        $this->assertNotNull($retrievedToken);
        $this->assertEquals('test_token', $retrievedToken->token);
        $this->assertEquals(EmailTokenType::LOGIN, $retrievedToken->tokenType);
        $this->assertEquals(self::TEST_USER_ID, $retrievedToken->userId);

        // Clean up
        $repository->delete($retrievedToken);
    }
}
