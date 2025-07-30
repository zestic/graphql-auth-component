<?php

declare(strict_types=1);

namespace Tests\Integration\DB\PDO;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\AuthCodeRepository;
use Zestic\GraphQL\AuthComponent\Entity\AuthCodeEntity;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class AuthCodeRepositoryTest extends DatabaseTestCase
{
    private AuthCodeRepository $repository;

    private ClientEntity $clientEntity;

    /**
     * Generate a UUID compatible with both MySQL and PostgreSQL
     */
    private function generateUuid(): string
    {
        // For PostgreSQL compatibility, generate a proper UUID
        // For MySQL, this will work as a string
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AuthCodeRepository(self::$pdo);

        // Seed the database with required test data
        $this->seedDatabase();

        // Create a test client entity
        $this->clientEntity = new ClientEntity();
        $this->clientEntity->setIdentifier(self::$testClientId);
        $this->clientEntity->setName(self::TEST_CLIENT_NAME);
        $this->clientEntity->setRedirectUri(['https://example.com/callback']);
        $this->clientEntity->setIsConfidential(true);
    }

    public function testGetNewAuthCode(): void
    {
        $authCode = $this->repository->getNewAuthCode();

        $this->assertInstanceOf(AuthCodeEntity::class, $authCode);
    }

    public function testPersistNewAuthCode(): void
    {
        // Generate a proper UUID for PostgreSQL compatibility
        $authCodeId = $this->generateUuid();

        $authCode = $this->repository->getNewAuthCode();
        $authCode->setIdentifier($authCodeId);
        $authCode->setUserIdentifier(self::$testUserId);
        $authCode->setClient($this->clientEntity);
        $authCode->setExpiryDateTime(new \DateTimeImmutable('+10 minutes'));
        $authCode->setRedirectUri('https://example.com/callback');

        // Add scopes
        $scope = new ScopeEntity('read');
        $authCode->addScope($scope);

        // Set PKCE parameters
        $authCode->setCodeChallenge('test_code_challenge');
        $authCode->setCodeChallengeMethod('S256');

        $this->repository->persistNewAuthCode($authCode);

        // Verify the auth code was persisted
        $stmt = self::$pdo->prepare('SELECT * FROM oauth_auth_codes WHERE id = ?');
        $stmt->execute([$authCodeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals($authCodeId, $result['id']);
        $this->assertEquals(self::$testUserId, $result['user_id']);
        $this->assertEquals(self::$testClientId, $result['client_id']);
        $this->assertEquals('https://example.com/callback', $result['redirect_uri']);
        $this->assertEquals('test_code_challenge', $result['code_challenge']);
        $this->assertEquals('S256', $result['code_challenge_method']);
        $this->assertEquals(0, $result['revoked']);
    }

    public function testRevokeAuthCode(): void
    {
        // Generate a proper UUID for PostgreSQL compatibility
        $authCodeId = $this->generateUuid();

        // First, persist an auth code
        $authCode = $this->repository->getNewAuthCode();
        $authCode->setIdentifier($authCodeId);
        $authCode->setUserIdentifier(self::$testUserId);
        $authCode->setClient($this->clientEntity);
        $authCode->setExpiryDateTime(new \DateTimeImmutable('+10 minutes'));
        $authCode->setRedirectUri('https://example.com/callback');

        $this->repository->persistNewAuthCode($authCode);

        // Now revoke it
        $this->repository->revokeAuthCode($authCodeId);

        // Verify it was revoked
        $stmt = self::$pdo->prepare('SELECT revoked FROM oauth_auth_codes WHERE id = ?');
        $stmt->execute([$authCodeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals(1, $result['revoked']);
    }

    public function testIsAuthCodeRevoked(): void
    {
        // Generate UUIDs for PostgreSQL compatibility
        $nonExistentId = $this->generateUuid();
        $validCodeId = $this->generateUuid();

        // Test with non-existent auth code
        $this->assertTrue($this->repository->isAuthCodeRevoked($nonExistentId));

        // Test with valid, non-revoked auth code
        $authCode = $this->repository->getNewAuthCode();
        $authCode->setIdentifier($validCodeId);
        $authCode->setUserIdentifier(self::$testUserId);
        $authCode->setClient($this->clientEntity);
        $authCode->setExpiryDateTime(new \DateTimeImmutable('+10 minutes'));
        $authCode->setRedirectUri('https://example.com/callback');

        $this->repository->persistNewAuthCode($authCode);
        $this->assertFalse($this->repository->isAuthCodeRevoked($validCodeId));

        // Test with revoked auth code
        $this->repository->revokeAuthCode($validCodeId);
        $this->assertTrue($this->repository->isAuthCodeRevoked($validCodeId));
    }

    public function testGenerateUniqueIdentifier(): void
    {
        $identifier1 = $this->repository->generateUniqueIdentifier();
        $identifier2 = $this->repository->generateUniqueIdentifier();

        $this->assertIsString($identifier1);
        $this->assertIsString($identifier2);
        $this->assertEquals(36, strlen($identifier1)); // UUID v4 length with hyphens
        $this->assertNotEquals($identifier1, $identifier2); // Should be unique

        // Verify it's a valid UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $identifier1
        );
    }

    public function testGenerateUniqueIdentifierWithCustomLength(): void
    {
        // Note: The AuthCodeRepository always generates UUID v4 format (36 chars)
        // regardless of the length parameter, since the database expects UUIDs
        $identifier = $this->repository->generateUniqueIdentifier(20);

        $this->assertEquals(36, strlen($identifier)); // UUID v4 length with hyphens
        $this->assertIsString($identifier);

        // Verify it's a valid UUID format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $identifier
        );
    }
}
