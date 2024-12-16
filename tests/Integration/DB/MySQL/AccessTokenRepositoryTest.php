<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Tests\Integration\DB\MySQL;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\MySQL\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class AccessTokenRepositoryTest extends DatabaseTestCase
{
    private AccessTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new AccessTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
    }

    public function testGetNewTokenAndPersist(): void
    {
        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $clientEntity->method('getIdentifier')->willReturn('test_client');

        $scope = new ScopeEntity('test_scope');

        $token = $this->repository->getNewToken($clientEntity, [$scope], 'user123');

        $this->assertInstanceOf(AccessTokenEntity::class, $token);
        $this->assertEquals('user123', $token->getUserIdentifier());
        $this->assertSame($clientEntity, $token->getClient());
        $this->assertCount(1, $token->getScopes());

        // Check if the token was persisted
        $stmt = self::$pdo->prepare("SELECT * FROM oauth_access_tokens WHERE id = :id");
        $stmt->execute(['id' => $token->getIdentifier()]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals('user123', $result['user_id']);
        $this->assertEquals('test_client', $result['client_id']);
        $this->assertStringContainsString('test_scope', $result['scopes']);
    }

    public function testRevokeAccessToken(): void
    {
        // First, create and persist a token
        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $clientEntity->method('getIdentifier')->willReturn('test_client');
        $token = $this->repository->getNewToken($clientEntity, [], 'user123');

        // Now revoke it
        $this->repository->revokeAccessToken($token->getIdentifier());

        // Check if it's revoked
        $this->assertTrue($this->repository->isAccessTokenRevoked($token->getIdentifier()));
    }

    public function testIsAccessTokenRevoked(): void
    {
        // Create a non-revoked token
        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $clientEntity->method('getIdentifier')->willReturn('test_client');
        $token = $this->repository->getNewToken($clientEntity, [], 'user123');

        // Check that it's not revoked
        $this->assertFalse($this->repository->isAccessTokenRevoked($token->getIdentifier()));

        // Revoke it
        $this->repository->revokeAccessToken($token->getIdentifier());

        // Check that it's now revoked
        $this->assertTrue($this->repository->isAccessTokenRevoked($token->getIdentifier()));
    }
}
