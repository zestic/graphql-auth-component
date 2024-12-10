<?php

namespace Tests\Integration\DB\MySQL;

use Tests\Integration\DatabaseTestCase;
use Zestic\GraphQL\AuthComponent\DB\MySQL\EmailTokenRepository;
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

    // Add more test methods as needed
}
