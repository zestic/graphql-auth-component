<?php

declare(strict_types=1);

namespace Tests\Unit\Event\Handler;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Event\Handler\SendVerificationEmailHandler;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;

class SendVerificationEmailHandlerTest extends TestCase
{
    private ClientRepositoryInterface $clientRepository;

    private MagicLinkTokenFactory $magicLinkTokenFactory;

    private SendVerificationLinkInterface $sendVerificationLink;

    private LoggerInterface $logger;

    private SendVerificationEmailHandler $handler;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->magicLinkTokenFactory = $this->createMock(MagicLinkTokenFactory::class);
        $this->sendVerificationLink = $this->createMock(SendVerificationLinkInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new SendVerificationEmailHandler(
            $this->clientRepository,
            $this->magicLinkTokenFactory,
            $this->sendVerificationLink,
            $this->logger
        );
    }

    public function testSuccessfulEmailSending()
    {
        $context = new RegistrationContext([
            'email' => 'test@example.com',
            'additionalData' => ['displayName' => 'Test User'],
        ]);

        $event = new UserRegisteredEvent(
            userId: '123',
            registrationContext: $context,
            clientId: 'test-client'
        );

        $client = $this->createMock(ClientEntity::class);
        $token = $this->createMock(MagicLinkToken::class);

        $this->clientRepository
            ->expects($this->once())
            ->method('getClientEntity')
            ->with('test-client')
            ->willReturn($client);

        $this->magicLinkTokenFactory
            ->expects($this->once())
            ->method('createRegistrationToken')
            ->with('123', $client, $context)
            ->willReturn($token);

        $this->sendVerificationLink
            ->expects($this->once())
            ->method('send')
            ->with($context, $token);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Verification email sent successfully', [
                'userId' => '123',
                'email' => 'test@example.com',
                'clientId' => 'test-client',
            ]);

        ($this->handler)($event);
    }

    public function testHandlerWithoutLogger()
    {
        // Test handler without explicit logger (should use NullLogger)
        $handler = new SendVerificationEmailHandler(
            $this->clientRepository,
            $this->magicLinkTokenFactory,
            $this->sendVerificationLink
        );

        $context = new RegistrationContext([
            'email' => 'test@example.com',
            'additionalData' => [],
        ]);

        $event = new UserRegisteredEvent(
            userId: '123',
            registrationContext: $context,
            clientId: 'test-client'
        );

        $client = $this->createMock(ClientEntity::class);
        $token = $this->createMock(MagicLinkToken::class);

        $this->clientRepository
            ->expects($this->once())
            ->method('getClientEntity')
            ->with('test-client')
            ->willReturn($client);

        $this->magicLinkTokenFactory
            ->expects($this->once())
            ->method('createRegistrationToken')
            ->willReturn($token);

        $this->sendVerificationLink
            ->expects($this->once())
            ->method('send');

        // Should not throw any exceptions
        $handler($event);
    }

    public function testInvalidClient()
    {
        $context = new RegistrationContext([
            'email' => 'test@example.com',
            'additionalData' => [],
        ]);

        $event = new UserRegisteredEvent(
            userId: '123',
            registrationContext: $context,
            clientId: 'invalid-client'
        );

        $this->clientRepository
            ->expects($this->once())
            ->method('getClientEntity')
            ->with('invalid-client')
            ->willReturn(null);

        $this->magicLinkTokenFactory
            ->expects($this->never())
            ->method('createRegistrationToken');

        $this->sendVerificationLink
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to send verification email: Invalid client', [
                'clientId' => 'invalid-client',
                'userId' => '123',
                'email' => 'test@example.com',
            ]);

        ($this->handler)($event);
    }

    public function testTokenCreationFailure()
    {
        $context = new RegistrationContext([
            'email' => 'test@example.com',
            'additionalData' => [],
        ]);

        $event = new UserRegisteredEvent(
            userId: '123',
            registrationContext: $context,
            clientId: 'test-client'
        );

        $client = $this->createMock(ClientEntity::class);
        $exception = new \RuntimeException('Token creation failed');

        $this->clientRepository
            ->expects($this->once())
            ->method('getClientEntity')
            ->with('test-client')
            ->willReturn($client);

        $this->magicLinkTokenFactory
            ->expects($this->once())
            ->method('createRegistrationToken')
            ->with('123', $client, $context)
            ->willThrowException($exception);

        $this->sendVerificationLink
            ->expects($this->never())
            ->method('send');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to send verification email', [
                'userId' => '123',
                'email' => 'test@example.com',
                'clientId' => 'test-client',
                'error' => 'Token creation failed',
                'exception' => $exception,
            ]);

        // Should not rethrow the exception
        ($this->handler)($event);
    }

    public function testEmailSendingFailure()
    {
        $context = new RegistrationContext([
            'email' => 'test@example.com',
            'additionalData' => [],
        ]);

        $event = new UserRegisteredEvent(
            userId: '123',
            registrationContext: $context,
            clientId: 'test-client'
        );

        $client = $this->createMock(ClientEntity::class);
        $token = $this->createMock(MagicLinkToken::class);
        $exception = new \RuntimeException('Email sending failed');

        $this->clientRepository
            ->expects($this->once())
            ->method('getClientEntity')
            ->with('test-client')
            ->willReturn($client);

        $this->magicLinkTokenFactory
            ->expects($this->once())
            ->method('createRegistrationToken')
            ->with('123', $client, $context)
            ->willReturn($token);

        $this->sendVerificationLink
            ->expects($this->once())
            ->method('send')
            ->with($context, $token)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Failed to send verification email', [
                'userId' => '123',
                'email' => 'test@example.com',
                'clientId' => 'test-client',
                'error' => 'Email sending failed',
                'exception' => $exception,
            ]);

        // Should not rethrow the exception
        ($this->handler)($event);
    }

    public function testHandlerWithComplexUserData()
    {
        $context = new RegistrationContext([
            'email' => 'complex@example.com',
            'additionalData' => [
                'displayName' => 'Complex User',
                'preferences' => ['theme' => 'dark', 'notifications' => true],
                'metadata' => ['source' => 'api', 'version' => '1.0'],
            ],
        ]);

        $event = new UserRegisteredEvent(
            userId: 'complex-user-456',
            registrationContext: $context,
            clientId: 'complex-client'
        );

        $client = $this->createMock(ClientEntity::class);
        $token = $this->createMock(MagicLinkToken::class);

        $this->clientRepository
            ->expects($this->once())
            ->method('getClientEntity')
            ->with('complex-client')
            ->willReturn($client);

        $this->magicLinkTokenFactory
            ->expects($this->once())
            ->method('createRegistrationToken')
            ->with('complex-user-456', $client, $context)
            ->willReturn($token);

        $this->sendVerificationLink
            ->expects($this->once())
            ->method('send')
            ->with($context, $token);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Verification email sent successfully', [
                'userId' => 'complex-user-456',
                'email' => 'complex@example.com',
                'clientId' => 'complex-client',
            ]);

        ($this->handler)($event);
    }
}
