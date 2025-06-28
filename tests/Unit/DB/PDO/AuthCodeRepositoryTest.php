<?php

declare(strict_types=1);

namespace Tests\Unit\DB\PDO;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\AuthCodeRepository;
use Zestic\GraphQL\AuthComponent\Entity\AuthCodeEntity;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;

class AuthCodeRepositoryTest extends TestCase
{
    private AuthCodeRepository $repository;

    private \PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create in-memory SQLite database for testing
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Create the oauth_auth_codes table
        $this->pdo->exec('
            CREATE TABLE oauth_auth_codes (
                id VARCHAR(100) PRIMARY KEY,
                user_id VARCHAR(255),
                client_id VARCHAR(255) NOT NULL,
                scopes TEXT,
                redirect_uri TEXT NOT NULL,
                revoked INTEGER DEFAULT 0,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ');

        $this->repository = new AuthCodeRepository($this->pdo);
    }

    public function testGetNewAuthCode(): void
    {
        $authCode = $this->repository->getNewAuthCode();

        $this->assertInstanceOf(AuthCodeEntity::class, $authCode);
    }

    public function testPersistNewAuthCode(): void
    {
        $authCode = $this->createAuthCodeEntity();

        $this->repository->persistNewAuthCode($authCode);

        // Verify the auth code was persisted
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_auth_codes WHERE id = ?');
        $stmt->execute([$authCode->getIdentifier()]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals($authCode->getIdentifier(), $result['id']);
        $this->assertEquals($authCode->getUserIdentifier(), $result['user_id']);
        $this->assertEquals($authCode->getClient()->getIdentifier(), $result['client_id']);
        $this->assertEquals($authCode->getRedirectUri(), $result['redirect_uri']);
        $this->assertEquals(0, $result['revoked']);
    }

    public function testRevokeAuthCode(): void
    {
        $authCode = $this->createAuthCodeEntity();
        $this->repository->persistNewAuthCode($authCode);

        $this->repository->revokeAuthCode($authCode->getIdentifier());

        // Verify the auth code was revoked
        $stmt = $this->pdo->prepare('SELECT revoked FROM oauth_auth_codes WHERE id = ?');
        $stmt->execute([$authCode->getIdentifier()]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $result['revoked']);
    }

    public function testIsAuthCodeRevoked(): void
    {
        $authCode = $this->createAuthCodeEntity();
        $this->repository->persistNewAuthCode($authCode);

        // Initially not revoked
        $this->assertFalse($this->repository->isAuthCodeRevoked($authCode->getIdentifier()));

        // After revoking
        $this->repository->revokeAuthCode($authCode->getIdentifier());
        $this->assertTrue($this->repository->isAuthCodeRevoked($authCode->getIdentifier()));
    }

    public function testIsAuthCodeRevokedForNonExistentCode(): void
    {
        // Non-existent codes should be considered revoked
        $this->assertTrue($this->repository->isAuthCodeRevoked('non-existent-code'));
    }

    public function testGenerateUniqueIdentifier(): void
    {
        $identifier1 = $this->repository->generateUniqueIdentifier();
        $identifier2 = $this->repository->generateUniqueIdentifier();

        $this->assertIsString($identifier1);
        $this->assertIsString($identifier2);
        $this->assertEquals(40, strlen($identifier1)); // Default length
        $this->assertNotEquals($identifier1, $identifier2); // Should be unique
    }

    public function testGenerateUniqueIdentifierWithCustomLength(): void
    {
        $identifier = $this->repository->generateUniqueIdentifier(20);

        $this->assertEquals(20, strlen($identifier));
    }

    private function createAuthCodeEntity(): AuthCodeEntity
    {
        $client = new ClientEntity();
        $client->setIdentifier('test-client-id');

        $scope = $this->createMock(ScopeEntityInterface::class);
        $scope->method('getIdentifier')->willReturn('read');

        $authCode = new AuthCodeEntity();
        $authCode->setIdentifier('test-auth-code-id');
        $authCode->setUserIdentifier('test-user-id');
        $authCode->setClient($client);
        $authCode->setRedirectUri('https://example.com/callback');
        $authCode->setExpiryDateTime(new \DateTimeImmutable('+10 minutes'));
        $authCode->addScope($scope);

        return $authCode;
    }
}
