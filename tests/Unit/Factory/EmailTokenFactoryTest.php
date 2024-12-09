<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenConfig;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;

class EmailTokenFactoryTest extends TestCase
{
    private EmailTokenFactory $factory;
    private EmailTokenConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new EmailTokenConfig(30, 60);
        $this->factory = new EmailTokenFactory($this->config);

        // Mock $_SERVER superglobal
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    public function testCreateRegistrationToken(): void
    {
        $userId = 'user123';
        $token = $this->factory->createRegistrationToken($userId);

        $this->assertInstanceOf(EmailToken::class, $token);
        $this->assertEquals(EmailTokenType::REGISTRATION, $token->tokenType);
        $this->assertEquals($userId, $token->userId);
        $this->assertEqualsWithDelta(
            (new \DateTime())->modify('+60 minutes'),
            $token->expiration,
            60 // Allow 1 minute difference due to execution time
        );
        $this->assertNotEmpty($token->token);
        $this->assertIsArray($token->userAgent);
        $this->assertArrayHasKey('ipAddress', $token->userAgent);
        $this->assertEquals('127.0.0.1', $token->userAgent['ipAddress']);
    }

    public function testCreateLoginToken(): void
    {
        $userId = 'user456';
        $token = $this->factory->createLoginToken($userId);

        $this->assertInstanceOf(EmailToken::class, $token);
        $this->assertEquals(EmailTokenType::LOGIN, $token->tokenType);
        $this->assertEquals($userId, $token->userId);
        $this->assertEqualsWithDelta(
            (new \DateTime())->modify('+30 minutes'),
            $token->expiration,
            60 // Allow 1 minute difference due to execution time
        );
        $this->assertNotEmpty($token->token);
        $this->assertIsArray($token->userAgent);
        $this->assertArrayHasKey('ipAddress', $token->userAgent);
        $this->assertEquals('127.0.0.1', $token->userAgent['ipAddress']);
    }
}
