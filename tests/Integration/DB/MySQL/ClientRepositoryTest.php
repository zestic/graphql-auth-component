<?php

declare(strict_types=1);

namespace Tests\Integration\DB\MySQL;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\MySQL\ClientRepository;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;

class ClientRepositoryTest extends DatabaseTestCase
{
    private ClientRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ClientRepository(self::$pdo);
    }

    public function testCreateAndGetClient(): void
    {
        $clientEntity = new ClientEntity(
            'test_client',
            'Test Client',
            'https://example.com/callback',
            true
        );

        // Test create
        $result = $this->repository->create($clientEntity);
        $this->assertTrue($result);

        // Test get
        $retrievedClient = $this->repository->getClientEntity('test_client');
        $this->assertInstanceOf(ClientEntity::class, $retrievedClient);
        $this->assertEquals('test_client', $retrievedClient->getIdentifier());
        $this->assertEquals('Test Client', $retrievedClient->getName());
        $this->assertEquals('https://example.com/callback', $retrievedClient->getRedirectUri());
        $this->assertTrue($retrievedClient->isConfidential());
    }

    public function testValidateClient(): void
    {
        $clientEntity = new ClientEntity(
            'test_client',
            'Test Client',
            'https://example.com/callback',
            true
        );
        $this->repository->create($clientEntity);

        // Test valid client
        $isValid = $this->repository->validateClient('test_client', null, null);
        $this->assertTrue($isValid);

        // Test invalid client
        $isValid = $this->repository->validateClient('non_existent_client', 'secret', 'authorization_code');
        $this->assertFalse($isValid);
    }

    public function testDeleteClient(): void
    {
        $clientEntity = new ClientEntity(
            'test_client',
            'Test Client',
            'https://example.com/callback',
            true
        );
        $this->repository->create($clientEntity);

        // Test delete
        $result = $this->repository->delete($clientEntity);
        $this->assertTrue($result);

        // Verify the client is soft deleted
        $retrievedClient = $this->repository->getClientEntity('test_client');
        $this->assertNull($retrievedClient);

        // Verify the client still exists in the database but is marked as deleted
        $stmt = self::$pdo->prepare('SELECT * FROM oauth_clients WHERE client_id = :clientId');
        $stmt->execute(['clientId' => 'test_client']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($result);
        $this->assertNotNull($result['deleted_at']);
    }

    protected function tearDown(): void
    {
        // Clean up the database after each test
        self::$pdo->exec('TRUNCATE TABLE oauth_clients');
        parent::tearDown();
    }
}
