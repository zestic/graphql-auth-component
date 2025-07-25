<?php

namespace Tests\Integration\DB\PDO;

use Carbon\CarbonImmutable;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\MagicLinkTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;

class MagicLinkTokenRepositoryTest extends DatabaseTestCase
{
    private MagicLinkTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new MagicLinkTokenRepository(self::$pdo);
    }

    public function testCreateMagicLinkToken(): void
    {
        $token = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::REGISTRATION,
            userId: '550e8400-e29b-41d4-a716-446655440003'
        );
        // Override the generated token with the expected test token
        $token->token = 'test_token';
        $result = $this->repository->create($token);
        $this->assertTrue($result);
        // Verify the token was created
        $schema = self::getSchemaPrefix();
        $stmt = self::$pdo->prepare("SELECT * FROM {$schema}magic_link_tokens WHERE token = ?");
        $stmt->execute([$token->token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertEquals($token->token, $row['token']);
        $this->assertEquals($token->userId, $row['user_id']);
        $this->assertEquals($token->tokenType->value, $row['token_type']);
    }

    public function testDelete(): void
    {
        // Arrange
        $token = 'test_token_'.uniqid();
        $userId = self::$driver === 'pgsql' ? '550e8400-e29b-41d4-a716-446655440004' : 'user_'.uniqid();
        $expiration = new \DateTimeImmutable('+1 hour');
        $tokenType = MagicLinkTokenType::REGISTRATION;
        $magicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse($expiration->format('Y-m-d H:i:s')),
            tokenType: $tokenType,
            userId: $userId
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;
        $this->repository->create($magicLinkToken);
        // Act
        $result = $this->repository->delete($magicLinkToken);
        // Assert
        $this->assertTrue($result);
        // Verify the token was deleted
        $schema = self::getSchemaPrefix();
        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM {$schema}magic_link_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
        // Test deleting by string
        $anotherToken = 'another_test_token_'.uniqid();
        $anotherMagicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse($expiration->format('Y-m-d H:i:s')),
            tokenType: $tokenType,
            userId: $userId
        );
        // Override the generated token with the expected test token
        $anotherMagicLinkToken->token = $anotherToken;
        $this->repository->create($anotherMagicLinkToken);
        $result = $this->repository->delete($anotherToken);
        $this->assertTrue($result);
        $stmt->execute([$anotherToken]);
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
    }

    public function testFindByToken(): void
    {
        // Arrange
        $token = 'test_token_'.uniqid();
        $userId = self::$driver === 'pgsql' ? '550e8400-e29b-41d4-a716-446655440004' : 'user_'.uniqid();
        $expiration = new \DateTimeImmutable('+1 hour');
        $tokenType = MagicLinkTokenType::REGISTRATION;
        $magicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse($expiration->format('Y-m-d H:i:s')),
            tokenType: $tokenType,
            userId: $userId
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;
        $this->repository->create($magicLinkToken);
        // Act
        $foundToken = $this->repository->findByUnexpiredToken($token);
        // Assert
        $this->assertInstanceOf(MagicLinkToken::class, $foundToken);
        $this->assertEquals($token, $foundToken->token);
        $this->assertEquals($userId, $foundToken->userId);
        $this->assertEquals($tokenType, $foundToken->tokenType);
        $this->assertEquals($expiration->format('Y-m-d H:i:s'), $foundToken->expiration->format('Y-m-d H:i:s'));
    }

    public function testFindByUnexpiredTokenReturnsNullForNonExistentToken(): void
    {
        // Act
        $foundToken = $this->repository->findByUnexpiredToken('non_existent_token');
        // Assert
        $this->assertNull($foundToken);
    }

    public function testFindByUnexpiredTokenReturnsNullForExpiredToken(): void
    {
        // Arrange
        $token = 'expired_token_'.uniqid();
        $userId = self::$driver === 'pgsql' ? '550e8400-e29b-41d4-a716-446655440004' : 'user_'.uniqid();
        $tokenType = MagicLinkTokenType::REGISTRATION;
        // Use the database's current timestamp
        $stmt = self::$pdo->query(self::$driver === 'pgsql' ? "SELECT NOW() - INTERVAL '1 hour' as expiration" : "SELECT NOW() - INTERVAL 1 HOUR as expiration");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $expirationFromDb = new \DateTimeImmutable($result['expiration']);
        $magicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse($expirationFromDb->format('Y-m-d H:i:s')),
            tokenType: $tokenType,
            userId: $userId
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;
        $this->repository->create($magicLinkToken);
        // Ensure some time has passed
        sleep(1);
        // Act
        $foundToken = $this->repository->findByUnexpiredToken($token);
        // Assert
        $this->assertNull($foundToken);
        // Verify the token exists but is considered expired
        $schema = self::getSchemaPrefix();
        $stmt = self::$pdo->prepare("SELECT * FROM {$schema}magic_link_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertEquals($token, $row['token']);
        $this->assertTrue(new \DateTimeImmutable($row['expiration']) < new \DateTimeImmutable());
    }

    public function testFindByTokenIncludesExpiredTokens(): void
    {
        // Arrange
        $token = 'expired_token_'.uniqid();
        $userId = self::$driver === 'pgsql' ? '550e8400-e29b-41d4-a716-446655440005' : 'user_'.uniqid();
        $tokenType = MagicLinkTokenType::LOGIN;
        // Use the database's current timestamp
        $stmt = self::$pdo->query(self::$driver === 'pgsql' ? "SELECT NOW() - INTERVAL '1 hour' as expiration" : "SELECT NOW() - INTERVAL 1 HOUR as expiration");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $expirationFromDb = new \DateTimeImmutable($result['expiration']);
        $magicLinkToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse($expirationFromDb->format('Y-m-d H:i:s')),
            tokenType: $tokenType,
            userId: $userId
        );
        // Override the generated token with the expected test token
        $magicLinkToken->token = $token;
        $this->repository->create($magicLinkToken);
        // Ensure some time has passed
        sleep(1);
        // Act
        $foundToken = $this->repository->findByToken($token);
        // Assert - should find the token even though it's expired
        $this->assertInstanceOf(MagicLinkToken::class, $foundToken);
        $this->assertEquals($token, $foundToken->token);
        $this->assertEquals($userId, $foundToken->userId);
        $this->assertEquals($tokenType, $foundToken->tokenType);
        $this->assertTrue($foundToken->isExpired());
    }
}
