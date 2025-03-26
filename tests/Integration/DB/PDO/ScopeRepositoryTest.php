<?php

declare(strict_types=1);

namespace Tests\Integration\DB\PDO;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\ScopeRepository;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class ScopeRepositoryTest extends DatabaseTestCase
{
    private ScopeRepository $scopeRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scopeRepository = new ScopeRepository(self::$pdo);

        // Seed client first
        $clientEntity = self::seedClientRepository();

        // Then seed scopes
        self::$pdo->exec("INSERT INTO " . (self::$driver === 'pgsql' ? 'graphql_auth_test.' : '') . "oauth_scopes (id, description) VALUES ('read', 'Read access'), ('write', 'Write access')");

        // Finally seed client scopes
        self::$pdo->exec("INSERT INTO " . (self::$driver === 'pgsql' ? 'graphql_auth_test.' : '') . "oauth_client_scopes (client_id, scope) VALUES ('" . $clientEntity->getIdentifier() . "', 'read'), ('" . $clientEntity->getIdentifier() . "', 'write')");
    }

    public function testGetScopeEntityByIdentifier(): void
    {
        $scope = $this->scopeRepository->getScopeEntityByIdentifier('read');
        $this->assertInstanceOf(ScopeEntity::class, $scope);
        $this->assertEquals('read', $scope->getIdentifier());

        $nonExistentScope = $this->scopeRepository->getScopeEntityByIdentifier('non_existent');
        $this->assertNull($nonExistentScope);
    }

    public function testFinalizeScopes(): void
    {
        $clientMock = $this->createMock(ClientEntityInterface::class);
        $clientMock->method('getIdentifier')->willReturn(self::$testClientId);

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
        $clientMock->method('getIdentifier')->willReturn(self::$testClientId);

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
        $table = self::$driver === 'pgsql' ? 'graphql_auth_test.oauth_scopes' : 'oauth_scopes';
        self::$pdo->exec("INSERT INTO {$table} (id, description) VALUES ('read', 'Read access')");
        self::$pdo->exec("INSERT INTO {$table} (id, description) VALUES ('write', 'Write access')");
        self::$pdo->exec("INSERT INTO {$table} (id, description) VALUES ('delete', 'Delete access')");

        // Insert client scopes
        $table = self::$driver === 'pgsql' ? 'graphql_auth_test.oauth_client_scopes' : 'oauth_client_scopes';
        self::$pdo->exec("INSERT INTO {$table} (client_id, scope) VALUES ('" . self::$testClientId . "', 'read')");
        self::$pdo->exec("INSERT INTO {$table} (client_id, scope) VALUES ('" . self::$testClientId . "', 'write')");
    }
}
