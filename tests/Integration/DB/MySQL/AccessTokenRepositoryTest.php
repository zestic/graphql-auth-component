<?php

declare(strict_types=1);

namespace Tests\Integration\DB\MySQL;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\MySQL\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class AccessTokenRepositoryTest extends DatabaseTestCase
{
    private AccessTokenRepository $repository;
    private ClientEntity $clientEntity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AccessTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        ); 

        $this->clientEntity = $this->getSeededClientEntity();
    }

    public function testGetNewTokenAndPersist(): void
    {
        $this->seedUserRepository();
        $this->seedClientRepository();

        $scope = new ScopeEntity('test_scope');

        $accessToken = $this->repository->getNewToken($this->clientEntity, [$scope], self::TEST_USER_ID);

        $this->assertInstanceOf(AccessTokenEntity::class, $accessToken);
        $this->assertEquals(self::TEST_USER_ID, $accessToken->getUserIdentifier());
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
        $this->assertEquals(self::TEST_USER_ID, $result['user_id']);
        $this->assertEquals('test_client', $result['client_id']);
        $this->assertStringContainsString('test_scope', $result['scopes']);
        $persistedExpiryTime = new \DateTimeImmutable($result['expires_at']);
        $this->assertEquals($dateTime->format('Y-m-d H:i:s'), $persistedExpiryTime->format('Y-m-d H:i:s'));
    }

    public function testRevokeAccessToken(): void
    {
        // First, create and persist a token
        $accessToken = $this->repository->getNewToken($this->clientEntity, [], self::TEST_USER_ID);
        $accessToken->setIdentifier($this->generateUniqueIdentifier());
        $this->repository->persistNewAccessToken($accessToken);

        // Now revoke it
        $this->repository->revokeAccessToken($accessToken->getIdentifier());

        // Check if it's revoked
        $this->assertTrue($this->repository->isAccessTokenRevoked($accessToken->getIdentifier()));
    }

    public function testIsAccessTokenRevoked(): void
    {
        // Create a non-revoked token
        $accessToken = $this->repository->getNewToken($this->clientEntity, [], self::TEST_USER_ID);
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
