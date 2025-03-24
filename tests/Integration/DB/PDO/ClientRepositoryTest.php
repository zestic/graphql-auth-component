<?php

declare(strict_types=1);

namespace Tests\Integration\DB\PDO;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;
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
        $clientEntity = $this->createClientEntity();

        // Test create
        $result = $this->repository->create($clientEntity);
        $this->assertTrue($result);

        // Test get
        $retrievedClient = $this->repository->getClientEntity(self::TEST_CLIENT_ID);
        $this->assertInstanceOf(ClientEntity::class, $retrievedClient);
        $this->assertEquals(self::TEST_CLIENT_ID, $retrievedClient->getIdentifier());
        $this->assertEquals(self::TEST_CLIENT_NAME, $retrievedClient->getName());
        $this->assertEquals('https://example.com/callback', $retrievedClient->getRedirectUri());
        $this->assertTrue($retrievedClient->isConfidential());
    }

    /**
     * @depends testCreateAndGetClient
     */
    public function testValidateClient(): void
    {
        $clientEntity = $this->createClientEntity();
        $this->repository->create($clientEntity);

        // Test valid client
        $isValid = $this->repository->validateClient(self::TEST_CLIENT_ID, null, null);
        $this->assertTrue($isValid);

        // Test invalid client
        $isValid = $this->repository->validateClient('non_existent_client', 'secret', 'authorization_code');
        $this->assertFalse($isValid);
    }

    /**
     * @depends testCreateAndGetClient
     */
    public function testDeleteClient(): void
    {
        // Create a client first
        $clientEntity = $this->createClientEntity();
        $this->repository->create($clientEntity);

        // Test delete
        $result = $this->repository->delete($clientEntity);
        $this->assertTrue($result);

        // Verify the client is soft deleted
        $retrievedClient = $this->repository->getClientEntity(self::TEST_CLIENT_ID);
        $this->assertNull($retrievedClient);

        // Verify the client still exists in the database but is marked as deleted
        $stmt = self::$pdo->prepare('SELECT * FROM oauth_clients WHERE client_id = :clientId');
        $stmt->execute(['clientId' => self::TEST_CLIENT_ID]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($result);
        $this->assertNotNull($result['deleted_at']);
    }

    private function createClientEntity(): ClientEntity
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier(self::TEST_CLIENT_ID);
        $clientEntity->setName(self::TEST_CLIENT_NAME);
        $clientEntity->setRedirectUri('https://example.com/callback');
        $clientEntity->setIsConfidential(true);

        return $clientEntity;
    }
}
