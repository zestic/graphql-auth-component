<?php

declare(strict_types=1);

namespace Tests\Integration\DB\PDO;

use Ramsey\Uuid\Uuid;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class AccessTokenRepositoryTest extends DatabaseTestCase
{
    private static string $otherUserId;

    private static string $nonExistentUserId;

    private AccessTokenRepository $repository;

    private ClientEntity $clientEntity;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$otherUserId = Uuid::uuid4()->toString();
        self::$nonExistentUserId = Uuid::uuid4()->toString();
        self::seedUserRepository();
        self::seedUserRepository(self::$otherUserId, 'other_email', 'other_display_name');
        self::seedClientRepository();
    }

    protected function setUp(): void
    {
        parent::setUp();
        self::seedClientRepository();

        $this->repository = new AccessTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );

        $this->clientEntity = $this->getSeededClientEntity();
    }

    public function testGetNewTokenAndPersist(): void
    {
        self::seedUserRepository();
        $scope = new ScopeEntity('test_scope');

        $accessToken = $this->repository->getNewToken($this->clientEntity, [$scope], self::$testUserId);

        $this->assertInstanceOf(AccessTokenEntity::class, $accessToken);
        $this->assertEquals(self::$testUserId, $accessToken->getUserIdentifier());
        $this->assertSame($this->clientEntity, $accessToken->getClient());
        $this->assertCount(1, $accessToken->getScopes());

        $dateTime = new \DateTimeImmutable('+1 minute');
        $accessToken->setExpiryDateTime($dateTime);
        $accessToken->setIdentifier($this->generateUniqueIdentifier());

        // Check if the token was persisted
        $this->repository->persistNewAccessToken($accessToken);
        $stmt = self::$pdo->prepare("SELECT * FROM oauth_access_tokens WHERE id = :id");
        $stmt->execute(['id' => $accessToken->getIdentifier()]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals(self::$testUserId, $result['user_id']);
        $this->assertEquals(self::getCurrentClientId(), $result['client_id']);
        $this->assertStringContainsString('test_scope', $result['scopes']);
        $persistedExpiryTime = new \DateTimeImmutable($result['expires_at']);
        $this->assertEquals($dateTime->format('Y-m-d H:i:s'), $persistedExpiryTime->format('Y-m-d H:i:s'));
    }

    public function testFindTokensByUserId(): void
    {
        self::seedUserRepository();
        self::seedUserRepository(self::$otherUserId, 'other@test.com', 'Other User');
        // Create and persist multiple tokens for the same user
        $scope = new ScopeEntity('test_scope');
        $token1 = $this->repository->getNewToken($this->clientEntity, [$scope], self::$testUserId);
        $token1->setExpiryDateTime(new \DateTimeImmutable('+1 hour'));
        $token1Id = Uuid::uuid4()->toString();
        $token1->setIdentifier($token1Id);
        $this->repository->persistNewAccessToken($token1);

        // Create a second token for the same user
        $token2 = $this->repository->getNewToken($this->clientEntity, [$scope], self::$testUserId);
        $token2->setExpiryDateTime(new \DateTimeImmutable('+2 hours'));
        $token2Id = Uuid::uuid4()->toString();
        $token2->setIdentifier($token2Id);
        $this->repository->persistNewAccessToken($token2);

        // Create a token for a different user
        $otherToken = $this->repository->getNewToken($this->clientEntity, [$scope], self::$otherUserId);
        $otherToken->setExpiryDateTime(new \DateTimeImmutable('+1 hour'));
        $otherTokenId = Uuid::uuid4()->toString();
        $otherToken->setIdentifier($otherTokenId);
        $this->repository->persistNewAccessToken($otherToken);

        // Test finding tokens
        $foundTokens = $this->repository->findTokensByUserId(self::$testUserId);

        $this->assertCount(2, $foundTokens);
        foreach ($foundTokens as $token) {
            $this->assertInstanceOf(AccessTokenEntity::class, $token);
            $this->assertEquals(self::$testUserId, $token->getUserIdentifier());
        }

        // Test finding tokens for user with no tokens
        $noTokens = $this->repository->findTokensByUserId(self::$nonExistentUserId);
        $this->assertEmpty($noTokens);
    }

    public function testRevokeAccessToken(): void
    {
        self::seedUserRepository();
        // First, create and persist a token
        $accessToken = $this->repository->getNewToken($this->clientEntity, [], self::$testUserId);
        $accessToken->setIdentifier($this->generateUniqueIdentifier());
        $this->repository->persistNewAccessToken($accessToken);

        // Now revoke it
        $this->repository->revokeAccessToken($accessToken->getIdentifier());

        // Check if it's revoked
        $this->assertTrue($this->repository->isAccessTokenRevoked($accessToken->getIdentifier()));
    }

    public function testIsAccessTokenRevoked(): void
    {
        self::seedUserRepository();
        // Create a non-revoked token
        $accessToken = $this->repository->getNewToken($this->clientEntity, [], self::$testUserId);
        $accessToken->setIdentifier($this->generateUniqueIdentifier());
        $this->repository->persistNewAccessToken($accessToken);

        // Check that it's not revoked
        $this->assertFalse($this->repository->isAccessTokenRevoked($accessToken->getIdentifier()));

        // Revoke it
        $this->repository->revokeAccessToken($accessToken->getIdentifier());

        // Check that it's now revoked
        $this->assertTrue($this->repository->isAccessTokenRevoked($accessToken->getIdentifier()));
    }
}
