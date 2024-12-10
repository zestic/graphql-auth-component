<?php

namespace Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenConfig;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class EmailTokenFactoryTest extends TestCase
{
    private EmailTokenFactory $factory;
    private EmailTokenConfig $config;
    private EmailTokenRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new EmailTokenConfig(30, 60);
        $this->repository = $this->createMock(EmailTokenRepositoryInterface::class);
        $this->factory = new EmailTokenFactory($this->config, $this->repository);
    }

    public function testCreateRegistrationToken()
    {
        $userId = 'user123';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $token = $this->factory->createRegistrationToken($userId);

        $this->assertInstanceOf(EmailToken::class, $token);
        $this->assertEquals(EmailTokenType::REGISTRATION, $token->tokenType);
        $this->assertEquals($userId, $token->userId);
        $this->assertGreaterThan(new \DateTime(), $token->expiration);
        $this->assertLessThanOrEqual(
            (new \DateTime())->modify('+' . $this->config->getRegistrationTimeOfLifeMinutes() . ' minutes'),
            $token->expiration
        );
    }

    public function testCreateLoginToken()
    {
        $userId = 'user456';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $token = $this->factory->createLoginToken($userId);

        $this->assertInstanceOf(EmailToken::class, $token);
        $this->assertEquals(EmailTokenType::LOGIN, $token->tokenType);
        $this->assertEquals($userId, $token->userId);
        $this->assertGreaterThan(new \DateTime(), $token->expiration);
        $this->assertLessThanOrEqual(
            (new \DateTime())->modify('+' . $this->config->getLoginTimeOfLifeMinutes() . ' minutes'),
            $token->expiration
        );
    }

    public function testCreateRegistrationTokenFailure()
    {
        $userId = 'user789';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create email token');

        $this->factory->createRegistrationToken($userId);
    }
}
