<?php

declare(strict_types=1);

namespace Tests\Integration\DB\MySQL;

use DateTimeImmutable;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\MySQL\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\RefreshTokenEntity;

class RefreshTokenRepositoryTest extends DatabaseTestCase
{
    private RefreshTokenRepository $repository;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::seedClientRepository();
        self::seedUserRepository();
        self::seedAccessTokenTable();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new RefreshTokenRepository(
            self::$pdo,
            self::$tokenConfig,
        );
    }

    public function testGetNewRefreshToken(): void
    {
        $token = $this->repository->getNewRefreshToken();
        
        $this->assertInstanceOf(RefreshTokenEntity::class, $token);
    }

    public function testPersistNewRefreshToken(): void
    {
        $refreshToken = new RefreshTokenEntity();
        $tokenId = $this->generateUniqueIdentifier();
        $refreshToken->setIdentifier($tokenId);
        $refreshToken->setClientIdentifier(self::TEST_CLIENT_ID);
        $refreshToken->setUserIdentifier(self::TEST_USER_ID);
        $refreshToken->setExpiryDateTime(new DateTimeImmutable('2024-12-19 15:34:10'));

        $accessToken = $this->createMock(AccessTokenEntityInterface::class);
        $accessToken->method('getIdentifier')->willReturn(self::TEST_ACCESS_TOKEN_ID);
        $refreshToken->setAccessToken($accessToken);

        $this->repository->persistNewRefreshToken($refreshToken);

        $stmt = self::$pdo->prepare('SELECT * FROM oauth_refresh_tokens WHERE id = :id');
        $stmt->execute(['id' => $tokenId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($result);
        $this->assertEquals($tokenId, $result['id']);
        $this->assertEquals(self::TEST_CLIENT_ID, $result['client_id']);
        $this->assertEquals(self::TEST_USER_ID, $result['user_id']);
        $this->assertEquals(self::TEST_ACCESS_TOKEN_ID, $result['access_token_id']);
        $this->assertEquals(0, $result['revoked']);
        $this->assertEquals('2024-12-19 15:34:10', $result['expires_at']);
    }

    public function testPersistNewRefreshTokenWithDuplicateIdThrowsException(): void
    {
        $this->expectException(UniqueTokenIdentifierConstraintViolationException::class);

        $tokenId = $this->generateUniqueIdentifier();
        $refreshToken = new RefreshTokenEntity();
        $refreshToken->setIdentifier($tokenId);
        $refreshToken->setExpiryDateTime(new DateTimeImmutable('2024-12-19 15:34:10'));
        $refreshToken->setClientIdentifier(self::TEST_CLIENT_ID);
        $refreshToken->setUserIdentifier(self::TEST_USER_ID);
        $accessToken = self::getSeededAccessToken();
        $refreshToken->setAccessToken($accessToken);

        // First insertion
        $this->repository->persistNewRefreshToken($refreshToken);
        
        // Second insertion with same ID should throw exception
        $this->repository->persistNewRefreshToken($refreshToken);
    }

    public function testRevokeRefreshToken(): void
    {
        $tokenId = $this->generateUniqueIdentifier();
        
        // First create a token
        $refreshToken = new RefreshTokenEntity();
        $refreshToken->setIdentifier($tokenId);
        $refreshToken->setExpiryDateTime(new DateTimeImmutable('2024-12-19 15:34:10'));
        $refreshToken->setClientIdentifier(self::TEST_CLIENT_ID);
        $refreshToken->setUserIdentifier(self::TEST_USER_ID);
        $accessToken = self::getSeededAccessToken();
        $refreshToken->setAccessToken($accessToken);

        $this->repository->persistNewRefreshToken($refreshToken);

        // Now revoke it
        $this->repository->revokeRefreshToken($tokenId);

        $stmt = self::$pdo->prepare('SELECT revoked FROM oauth_refresh_tokens WHERE id = :id');
        $stmt->execute(['id' => $tokenId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(1, $result['revoked']);
    }

    public function testIsRefreshTokenRevoked(): void
    {
        // Test with non-existent token
        $this->assertTrue($this->repository->isRefreshTokenRevoked($this->generateUniqueIdentifier()));

        // Create and test non-revoked token
        $tokenId = $this->generateUniqueIdentifier();
        $refreshToken = new RefreshTokenEntity();
        $refreshToken->setIdentifier($tokenId);
        $refreshToken->setExpiryDateTime(new DateTimeImmutable('2024-12-19 15:34:10'));
        $refreshToken->setClientIdentifier(self::TEST_CLIENT_ID);
        $refreshToken->setUserIdentifier(self::TEST_USER_ID);
        $accessToken = self::getSeededAccessToken();
        $refreshToken->setAccessToken($accessToken);

        $this->repository->persistNewRefreshToken($refreshToken);
        $this->assertFalse($this->repository->isRefreshTokenRevoked($tokenId));

        // Revoke and test again
        $this->repository->revokeRefreshToken($tokenId);
        $this->assertTrue($this->repository->isRefreshTokenRevoked($tokenId));
    }

    protected function tearDown(): void
    {
        self::$pdo->exec('TRUNCATE TABLE oauth_refresh_tokens');
        parent::tearDown();
    }
}