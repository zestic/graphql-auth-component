<?php

namespace Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

class MagicLinkTokenFactoryTest extends TestCase
{
    private MagicLinkTokenFactory $factory;

    private TokenConfig $config;

    private MagicLinkTokenRepositoryInterface $repository;

    private ClientEntity $client;

    private RegistrationContext $registrationContext;

    private MagicLinkContext $magicLinkContext;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new TokenConfig(30, 60, 90, 120);
        $this->repository = $this->createMock(MagicLinkTokenRepositoryInterface::class);

        // Create a test client
        $this->client = new ClientEntity();
        $this->client->setIdentifier('test-client');
        $this->client->setName('Test Client');
        $this->client->setRedirectUri('https://example.com/callback');
        $this->client->setIsConfidential(true);

        // Create test contexts with required PKCE data
        $this->registrationContext = new RegistrationContext([
            'clientId' => 'test-client',
            'codeChallenge' => 'test-challenge',
            'codeChallengeMethod' => 'S256',
            'redirectUri' => 'https://example.com/callback',
            'state' => 'test-state',
            'email' => 'test@example.com',
        ]);

        $this->magicLinkContext = new MagicLinkContext([
            'clientId' => 'test-client',
            'codeChallenge' => 'test-challenge',
            'codeChallengeMethod' => 'S256',
            'redirectUri' => 'https://example.com/callback',
            'state' => 'test-state',
            'email' => 'test@example.com',
        ]);

        $this->factory = new MagicLinkTokenFactory($this->config, $this->repository);
    }

    public function testCreateRegistrationToken()
    {
        $userId = 'user123';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $token = $this->factory->createRegistrationToken($userId, $this->client, $this->registrationContext);

        $this->assertInstanceOf(MagicLinkToken::class, $token);
        $this->assertEquals(MagicLinkTokenType::REGISTRATION, $token->tokenType);
        $this->assertEquals($userId, $token->userId);
        $this->assertGreaterThan(new \DateTime(), $token->expiration);
        $this->assertLessThanOrEqual(
            (new \DateTime())->modify('+' . $this->config->getRegistrationTTLMinutes() . ' minutes'),
            $token->expiration
        );
    }

    public function testCreateLoginToken()
    {
        $userId = 'user456';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(true);

        $token = $this->factory->createLoginToken($userId, $this->client, $this->magicLinkContext);

        $this->assertInstanceOf(MagicLinkToken::class, $token);
        $this->assertEquals(MagicLinkTokenType::LOGIN, $token->tokenType);
        $this->assertEquals($userId, $token->userId);
        $this->assertGreaterThan(new \DateTime(), $token->expiration);
        $this->assertLessThanOrEqual(
            (new \DateTime())->modify('+' . $this->config->getLoginTTLMinutes() . ' minutes'),
            $token->expiration
        );
    }

    public function testCreateRegistrationTokenThrowsExceptionOnFailure()
    {
        $userId = 'user789';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create magic link token');

        $this->factory->createRegistrationToken($userId, $this->client, $this->registrationContext);
    }

    public function testCreateLoginTokenThrowsExceptionOnFailure()
    {
        $userId = 'user101';

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Failed to create magic link token');

        $this->factory->createLoginToken($userId, $this->client, $this->magicLinkContext);
    }
}
