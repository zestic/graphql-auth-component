<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationEmailInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\RegisterUser;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class RegisterUserTest extends TestCase
{
    private EmailTokenFactory $emailTokenFactory;
    private SendVerificationEmailInterface $sendRegistrationVerification;
    private UserRepositoryInterface $userRepository;
    private RegisterUser $registerUser;

    protected function setUp(): void
    {
        $this->emailTokenFactory = $this->createMock(EmailTokenFactory::class);
        $this->sendRegistrationVerification = $this->createMock(SendVerificationEmailInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);

        $this->registerUser = new RegisterUser(
            $this->emailTokenFactory,
            $this->sendRegistrationVerification,
            $this->userRepository
        );
    }

    public function testSuccessfulRegistration()
    {
        $context = new RegistrationContext('test@zestic.com', ['displayName' => 'Test User']);
        $userId = '123';
        $token = $this->createMock(EmailToken::class);

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(false);
        $this->userRepository->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('create')->willReturn($userId);
        $this->emailTokenFactory->expects($this->once())->method('createRegistrationToken')->willReturn($token);
        $this->sendRegistrationVerification->expects($this->once())->method('send');
        $this->userRepository->expects($this->once())->method('commit');

        $result = $this->registerUser->register($context);

        $this->assertTrue($result['success']);
        $this->assertEquals('Email registered successfully', $result['message']);
        $this->assertEquals('EMAIL_REGISTERED', $result['code']);
    }

    public function testRegistrationWithExistingEmail()
    {
        $context = new RegistrationContext('existing@zestic.com', []);

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(true);
        $this->userRepository->expects($this->never())->method('beginTransaction');
        $this->userRepository->expects($this->never())->method('create');

        $result = $this->registerUser->register($context);

        $this->assertFalse($result['success']);
        $this->assertEquals('Email already registered', $result['message']);
        $this->assertEquals('EMAIL_IN_SYSTEM', $result['code']);
    }

    public function testRegistrationWithSystemError()
    {
        $context = new RegistrationContext('test@zestic.com', []);

        $this->userRepository->expects($this->once())->method('emailExists')->willReturn(false);
        $this->userRepository->expects($this->once())->method('beginTransaction');
        $this->userRepository->expects($this->once())->method('create')->willThrowException(new \Exception('Database error'));
        $this->userRepository->expects($this->once())->method('rollback');

        $result = $this->registerUser->register($context);

        $this->assertFalse($result['success']);
        $this->assertEquals('Registration failed due to a system error', $result['message']);
        $this->assertEquals('SYSTEM_ERROR', $result['code']);
    }
}
