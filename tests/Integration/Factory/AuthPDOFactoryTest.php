<?php

declare(strict_types=1);

namespace Tests\Integration\Factory;

use PDO;
use Psr\Container\ContainerInterface;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOFactory;

class AuthPDOFactoryTest extends DatabaseTestCase
{
    public function testInvoke(): void
    {
        // Use test database environment variables
        putenv('AUTH_DB_HOST=' . $_ENV['TEST_DB_HOST']);
        putenv('AUTH_DB_NAME=' . $_ENV['TEST_DB_NAME']);
        putenv('AUTH_DB_USER=' . $_ENV['TEST_DB_USER']);
        putenv('AUTH_DB_PASS=' . $_ENV['TEST_DB_PASS']);
        putenv('AUTH_DB_PORT=' . $_ENV['TEST_DB_PORT']);
        putenv('AUTH_DB_DRIVER=' . $_ENV['TEST_DB_DRIVER']);

        $factory = new AuthPDOFactory();
        $pdo = $factory($this->createMock(ContainerInterface::class));

        $this->assertInstanceOf(PDO::class, $pdo);

        // Test connection by running a simple query
        $statement = $pdo->query('SELECT 1');
        $this->assertNotFalse($statement);
        $result = $statement->fetch(PDO::FETCH_COLUMN);
        $this->assertEquals('1', $result);
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        putenv('AUTH_DB_HOST');
        putenv('AUTH_DB_NAME');
        putenv('AUTH_DB_USER');
        putenv('AUTH_DB_PASS');
        putenv('AUTH_DB_PORT');

        parent::tearDown();
    }
}
