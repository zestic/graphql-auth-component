<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Interactor\ValidateRegistration;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ValidateRegistrationTest extends TestCase
{
    private ValidateRegistration $validateRegistration;
    private EmailTokenRepositoryInterface $emailTokenRepository;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        $this->emailTokenRepository = $this->createMock(EmailTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->validateRegistration = new ValidateRegistration(
            $this->emailTokenRepository,
            $this->userRepository
        );
    }

    public function testValidateWithValidToken(): void
    {
        $token = 'valid_token';
        $userId = 'user123';

        $emailToken = new EmailToken(
            new \DateTime('+1 hour'),
            $token,
            EmailTokenType::REGISTRATION,
            $userId
        );

        $user = new User([], 'Test User', 'test@example.com', $userId);

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $this->userRepository->expects($this->once())
            ->method('update')
            ->with($this->callback(function ($updatedUser) {
                return $updatedUser->verifiedAt !== null;
            }))
            ->willReturn(true);

        $this->emailTokenRepository->expects($this->once())
            ->method('delete')
            ->with($token);

        $result = $this->validateRegistration->validate($token);

        $this->assertTrue($result);
    }

    public function testValidateWithInvalidToken(): void
    {
        $token = 'invalid_token';

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn(null);

        $result = $this->validateRegistration->validate($token);

        $this->assertFalse($result);
    }

    public function testValidateWithNonRegistrationToken(): void
    {
        $token = 'non_registration_token';
        $userId = 'user123';

        $emailToken = new EmailToken(
            new \DateTime('+1 hour'),
            $token,
            EmailTokenType::LOGIN,
            $userId
        );

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $result = $this->validateRegistration->validate($token);

        $this->assertFalse($result);
    }

    public function testValidateWithNonExistentUser(): void
    {
        $token = 'valid_token';
        $userId = 'non_existent_user';

        $emailToken = new EmailToken(
            new \DateTime('+1 hour'),
            $token,
            EmailTokenType::REGISTRATION,
            $userId
        );

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn(null);

        $result = $this->validateRegistration->validate($token);

        $this->assertFalse($result);
    }

    public function testValidateWithAlreadyVerifiedUser(): void
    {
        $token = 'valid_token';
        $userId = 'user123';

        $emailToken = new EmailToken(
            new \DateTime('+1 hour'),
            $token,
            EmailTokenType::REGISTRATION,
            $userId
        );

        $user = new User([], 'Test User', 'test@example.com', $userId, new \DateTime());

        $this->emailTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($emailToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $result = $this->validateRegistration->validate($token);

        $this->assertFalse($result);
    }
}
