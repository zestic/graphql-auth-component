<?php

declare(strict_types=1);

namespace Tests\Unit\DB\PDO;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;

class ClientRepositoryWithSecretsTest extends TestCase
{
    private ClientRepository $repository;
    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        
        // Create the oauth_clients table with client_secret column
        $this->pdo->exec('
            CREATE TABLE oauth_clients (
                client_id VARCHAR(255) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                client_secret VARCHAR(255),
                redirect_uri TEXT,
                is_confidential INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                deleted_at DATETIME
            )
        ');
        
        $this->repository = new ClientRepository($this->pdo);
    }

    public function testCreateConfidentialClientWithSecret(): void
    {
        $client = $this->createConfidentialClientEntity();
        $client->setClientSecret('my-secret-key');
        
        $result = $this->repository->create($client);
        
        $this->assertTrue($result);
        
        // Verify the client was created with hashed secret
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_clients WHERE client_id = ?');
        $stmt->execute([$client->getIdentifier()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($row);
        $this->assertEquals($client->getIdentifier(), $row['client_id']);
        $this->assertEquals($client->getName(), $row['name']);
        $this->assertNotNull($row['client_secret']);
        $this->assertNotEquals('my-secret-key', $row['client_secret']); // Should be hashed
        $this->assertTrue(password_verify('my-secret-key', $row['client_secret']));
        $this->assertEquals(1, $row['is_confidential']);
    }

    public function testCreatePublicClientWithoutSecret(): void
    {
        $client = $this->createPublicClientEntity();
        
        $result = $this->repository->create($client);
        
        $this->assertTrue($result);
        
        // Verify the client was created without secret
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_clients WHERE client_id = ?');
        $stmt->execute([$client->getIdentifier()]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->assertNotFalse($row);
        $this->assertEquals($client->getIdentifier(), $row['client_id']);
        $this->assertNull($row['client_secret']);
        $this->assertEquals(0, $row['is_confidential']);
    }

    public function testValidateConfidentialClientWithCorrectSecret(): void
    {
        $client = $this->createConfidentialClientEntity();
        $client->setClientSecret('correct-secret');
        $this->repository->create($client);
        
        $isValid = $this->repository->validateClient(
            $client->getIdentifier(),
            'correct-secret',
            'authorization_code'
        );
        
        $this->assertTrue($isValid);
    }

    public function testValidateConfidentialClientWithIncorrectSecret(): void
    {
        $client = $this->createConfidentialClientEntity();
        $client->setClientSecret('correct-secret');
        $this->repository->create($client);
        
        $isValid = $this->repository->validateClient(
            $client->getIdentifier(),
            'wrong-secret',
            'authorization_code'
        );
        
        $this->assertFalse($isValid);
    }

    public function testValidatePublicClientWithoutSecret(): void
    {
        $client = $this->createPublicClientEntity();
        $this->repository->create($client);
        
        $isValid = $this->repository->validateClient(
            $client->getIdentifier(),
            null,
            'authorization_code'
        );
        
        $this->assertTrue($isValid);
    }

    public function testValidateNonExistentClient(): void
    {
        $isValid = $this->repository->validateClient(
            'non-existent-client',
            'any-secret',
            'authorization_code'
        );
        
        $this->assertFalse($isValid);
    }

    public function testGetClientEntityWithSecret(): void
    {
        $client = $this->createConfidentialClientEntity();
        $client->setClientSecret('test-secret');
        $this->repository->create($client);
        
        $retrievedClient = $this->repository->getClientEntity($client->getIdentifier());
        
        $this->assertNotNull($retrievedClient);
        $this->assertEquals($client->getIdentifier(), $retrievedClient->getIdentifier());
        $this->assertEquals($client->getName(), $retrievedClient->getName());
        $this->assertTrue($retrievedClient->isConfidential());
    }

    public function testClientSecretGetterAndSetter(): void
    {
        $client = new ClientEntity();
        
        $this->assertNull($client->getClientSecret());
        
        $client->setClientSecret('test-secret');
        $this->assertEquals('test-secret', $client->getClientSecret());
        
        $client->setClientSecret(null);
        $this->assertNull($client->getClientSecret());
    }

    private function createConfidentialClientEntity(): ClientEntity
    {
        $client = new ClientEntity();
        $client->setIdentifier('confidential-client-id');
        $client->setName('Test Confidential Client');
        $client->setRedirectUri('https://example.com/callback');
        $client->setIsConfidential(true);
        
        return $client;
    }

    private function createPublicClientEntity(): ClientEntity
    {
        $client = new ClientEntity();
        $client->setIdentifier('public-client-id');
        $client->setName('Test Public Client');
        $client->setRedirectUri('myapp://callback');
        $client->setIsConfidential(false);
        
        return $client;
    }
}
