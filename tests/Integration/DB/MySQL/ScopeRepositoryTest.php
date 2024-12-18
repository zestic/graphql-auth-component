<?php

declare(strict_types=1);

namespace Tests\Integration\DB\MySQL;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\MySQL\ScopeRepository;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class ScopeRepositoryTest extends DatabaseTestCase
{
    private ScopeRepository $scopeRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopeRepository = new ScopeRepository(self::$pdo);
    }

    public function testGetScopeEntityByIdentifier(): void
    {
        // Seed the database with test data
        $this->seedDatabase();

        $scope = $this->scopeRepository->getScopeEntityByIdentifier('read');
        $this->assertInstanceOf(ScopeEntity::class, $scope);
        $this->assertEquals('read', $scope->getIdentifier());
        $this->assertEquals('Read access', $scope->getDescription());

        $nonExistentScope = $this->scopeRepository->getScopeEntityByIdentifier('non_existent');
        $this->assertNull($nonExistentScope);
    }

    public function testFinalizeScopes(): void
    {
        $clientMock = $this->createMock(ClientEntityInterface::class);
        $clientMock->method('getIdentifier')->willReturn('test_client');

        $scopes = [
            new ScopeEntity('read'),
            new ScopeEntity('write'),
            new ScopeEntity('delete'),
        ];

        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, 'authorization_code', $clientMock);

        $this->assertCount(2, $finalizedScopes);
        $this->assertEquals('read', $finalizedScopes[0]->getIdentifier());
        $this->assertEquals('write', $finalizedScopes[1]->getIdentifier());
    }

    public function testFinalizeScopesWithEmptyArray(): void
    {
        $clientMock = $this->createMock(ClientEntityInterface::class);
        $clientMock->method('getIdentifier')->willReturn('test_client');

        $emptyScopes = [];
        $finalizedScopes = $this->scopeRepository->finalizeScopes(
            $emptyScopes,
            'authorization_code',
            $clientMock
        );

        $this->assertIsArray($finalizedScopes);
        $this->assertEmpty($finalizedScopes);
    }

    protected function seedDatabase(): void
    {
        $this->seedClientRepository();

        // Insert test scopes
        self::$pdo->exec("INSERT INTO oauth_scopes (id, description) VALUES ('read', 'Read access')");
        self::$pdo->exec("INSERT INTO oauth_scopes (id, description) VALUES ('write', 'Write access')");
        self::$pdo->exec("INSERT INTO oauth_scopes (id, description) VALUES ('delete', 'Delete access')");

        // Insert client scopes
        self::$pdo->exec("INSERT INTO oauth_client_scopes (client_id, scope) VALUES ('test_client', 'read')");
        self::$pdo->exec("INSERT INTO oauth_client_scopes (client_id, scope) VALUES ('test_client', 'write')");
    }
}
