<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Contract\UserCreatedHookInterface;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;
use Zestic\GraphQL\AuthComponent\Interactor\RegisterUser;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class RegisterUserTest extends TestCase
{
    private ClientRepositoryInterface $clientRepository;

    private EventDispatcherInterface $eventDispatcher;

    private UserCreatedHookInterface $userCreatedHook;

    private UserRepositoryInterface $userRepository;

    private RegisterUser $registerUser;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->userCreatedHook = $this->createMock(UserCreatedHookInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);

        // Mock the client repository to return a client entity
        $mockClient = $this->createMock(ClientEntity::class);
        $this->clientRepository->method('getClientEntity')->willReturn($mockClient);

        $this->registerUser = new RegisterUser(
            $this->clientRepository,
            $this->eventDispatcher,
            $this->userCreatedHook,
            $this->userRepository
        );
    }

    public function testSuccessfulRegistration()
    {
        $context = new RegistrationContext([
            'clientId' => 'test-client',
            'email' => 'test@zestic.com',
            'additionalData' => ['displayName' => 'Test User'],
        ]);
        $userId = '123';

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(false);
        $this->userRepository->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('create')->willReturn($userId);
        $this->userCreatedHook->expects($this->once())
            ->method('execute')
            ->with($context, $userId);
        $this->userRepository->expects($this->once())->method('commit');

        // Verify that UserRegisteredEvent is dispatched with correct data
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) use ($context, $userId) {
                return $event instanceof UserRegisteredEvent
                    && $event->getUserId() === $userId
                    && $event->getRegistrationContext() === $context
                    && $event->getClientId() === 'test-client'
                    && $event->getEmail() === 'test@zestic.com';
            }));

        $result = $this->registerUser->register($context);

        $this->assertTrue($result['success']);
        $this->assertEquals('Email registered successfully', $result['message']);
        $this->assertEquals('EMAIL_REGISTERED', $result['code']);
    }

    public function testRegistrationWithExistingEmail()
    {
        $context = new RegistrationContext([
            'clientId' => 'test-client',
            'email' => 'existing@zestic.com',
            'additionalData' => [],
        ]);

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(true);
        $this->userRepository->expects($this->never())->method('beginTransaction');
        $this->userRepository->expects($this->never())->method('create');
        $this->userCreatedHook->expects($this->never())->method('execute');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->registerUser->register($context);

        $this->assertFalse($result['success']);
        $this->assertEquals('Email already registered', $result['message']);
        $this->assertEquals('EMAIL_IN_SYSTEM', $result['code']);
    }

    public function testRegistrationWithSystemError()
    {
        $context = new RegistrationContext([
            'clientId' => 'test-client',
            'email' => 'test@zestic.com',
            'additionalData' => [],
        ]);

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(false);
        $this->userRepository->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('create')->willThrowException(new \Exception('Database error'));
        $this->userCreatedHook->expects($this->never())->method('execute');
        $this->userRepository->expects($this->once())->method('rollback');
        $this->eventDispatcher->expects($this->never())->method('dispatch');

        $result = $this->registerUser->register($context);

        $this->assertFalse($result['success']);
        $this->assertEquals('Registration failed due to a system error', $result['message']);
        $this->assertEquals('SYSTEM_ERROR', $result['code']);
    }

    public function testUserCreatedHookIsCalledWithCorrectParameters()
    {
        $context = new RegistrationContext([
            'clientId' => 'test-client',
            'email' => 'hook@zestic.com',
            'additionalData' => ['displayName' => 'Hook User', 'customField' => 'value'],
        ]);
        $userId = 'hook-user-123';

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(false);
        $this->userRepository->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('create')->willReturn($userId);

        // This is the main assertion - verify the hook is called with exact parameters
        $this->userCreatedHook->expects($this->once())
            ->method('execute')
            ->with(
                $this->equalTo($context),
                $this->equalTo($userId)
            );

        $this->userRepository->expects($this->once())->method('commit');
        $this->eventDispatcher->expects($this->once())->method('dispatch');

        $result = $this->registerUser->register($context);

        $this->assertTrue($result['success']);
    }
}
