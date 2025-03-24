<?php

namespace Tests\Integration\DB\PDO;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\PDO\EmailTokenRepository;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;

class EmailTokenRepositoryTest extends DatabaseTestCase
{
    private EmailTokenRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EmailTokenRepository(self::$pdo);
    }

    public function testCreateEmailToken(): void
    {
        $token = new EmailToken(
            new \DateTime('+1 hour'),
            'test_token',
            EmailTokenType::REGISTRATION,
            'user123'
        );
        $result = $this->repository->create($token);
        $this->assertTrue($result);
        // Verify the token was created
        $stmt = self::$pdo->prepare("SELECT * FROM email_tokens WHERE token = ?");
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
        $userId = 'user_'.uniqid();
        $expiration = new \DateTimeImmutable('+1 hour');
        $tokenType = EmailTokenType::REGISTRATION;
        $emailToken = new EmailToken(
            $expiration,
            $token,
            $tokenType,
            $userId
        );
        $this->repository->create($emailToken);
        // Act
        $result = $this->repository->delete($emailToken);
        // Assert
        $this->assertTrue($result);
        // Verify the token was deleted
        $stmt = self::$pdo->prepare("SELECT COUNT(*) FROM email_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
        // Test deleting by string
        $anotherToken = 'another_test_token_'.uniqid();
        $anotherEmailToken = new EmailToken(
            $expiration,
            $anotherToken,
            $tokenType,
            $userId
        );
        $this->repository->create($anotherEmailToken);
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
        $userId = 'user_'.uniqid();
        $expiration = new \DateTimeImmutable('+1 hour');
        $tokenType = EmailTokenType::REGISTRATION;
        $emailToken = new EmailToken(
            $expiration,
            $token,
            $tokenType,
            $userId
        );
        $this->repository->create($emailToken);
        // Act
        $foundToken = $this->repository->findByToken($token);
        // Assert
        $this->assertInstanceOf(EmailToken::class, $foundToken);
        $this->assertEquals($token, $foundToken->token);
        $this->assertEquals($userId, $foundToken->userId);
        $this->assertEquals($tokenType, $foundToken->tokenType);
        $this->assertEquals($expiration->format('Y-m-d H:i:s'), $foundToken->expiration->format('Y-m-d H:i:s'));
    }

    public function testFindByTokenReturnsNullForNonExistentToken(): void
    {
        // Act
        $foundToken = $this->repository->findByToken('non_existent_token');
        // Assert
        $this->assertNull($foundToken);
    }

    public function testFindByTokenReturnsNullForExpiredToken(): void
    {
        // Arrange
        $token = 'expired_token_'.uniqid();
        $userId = 'user_'.uniqid();
        $tokenType = EmailTokenType::REGISTRATION;
        // Use the database's current timestamp
        $stmt = self::$pdo->query("SELECT NOW() - INTERVAL 1 HOUR as expiration");
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $expirationFromDb = new \DateTimeImmutable($result['expiration']);
        $emailToken = new EmailToken(
            $expirationFromDb,
            $token,
            $tokenType,
            $userId
        );
        $this->repository->create($emailToken);
        // Ensure some time has passed
        sleep(1);
        // Act
        $foundToken = $this->repository->findByToken($token);
        // Assert
        $this->assertNull($foundToken);
        // Verify the token exists but is considered expired
        $stmt = self::$pdo->prepare("SELECT * FROM email_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertNotFalse($row);
        $this->assertEquals($token, $row['token']);
        $this->assertTrue(new \DateTimeImmutable($row['expiration']) < new \DateTimeImmutable());
    }
}
