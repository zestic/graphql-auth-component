<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Contract\UserCreatedHookInterface;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\RegisterUser;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class RegisterUserTest extends TestCase
{
    private ClientRepositoryInterface $clientRepository;

    private MagicLinkTokenFactory $magicLinkTokenFactory;

    private SendVerificationLinkInterface $sendRegistrationVerification;

    private UserCreatedHookInterface $userCreatedHook;

    private UserRepositoryInterface $userRepository;

    private RegisterUser $registerUser;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->magicLinkTokenFactory = $this->createMock(MagicLinkTokenFactory::class);
        $this->sendRegistrationVerification = $this->createMock(SendVerificationLinkInterface::class);
        $this->userCreatedHook = $this->createMock(UserCreatedHookInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);

        // Mock the client repository to return a client entity
        $mockClient = $this->createMock(ClientEntity::class);
        $this->clientRepository->method('getClientEntity')->willReturn($mockClient);

        $this->registerUser = new RegisterUser(
            $this->clientRepository,
            $this->magicLinkTokenFactory,
            $this->sendRegistrationVerification,
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
        $token = $this->createMock(MagicLinkToken::class);

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(false);
        $this->userRepository->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('create')->willReturn($userId);
        $this->userCreatedHook->expects($this->once())
            ->method('execute')
            ->with($context, $userId);
        $this->magicLinkTokenFactory->expects($this->once())->method('createRegistrationToken')->willReturn($token);
        $this->sendRegistrationVerification->expects($this->once())->method('send');
        $this->userRepository->expects($this->once())->method('commit');

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
        $token = $this->createMock(MagicLinkToken::class);

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

        $this->magicLinkTokenFactory->expects($this->once())->method('createRegistrationToken')->willReturn($token);
        $this->sendRegistrationVerification->expects($this->once())->method('send');
        $this->userRepository->expects($this->once())->method('commit');

        $result = $this->registerUser->register($context);

        $this->assertTrue($result['success']);
    }
}
