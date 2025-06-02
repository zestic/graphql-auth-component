<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Interactor\ValidateRegistration;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ValidateRegistrationTest extends TestCase
{
    private ValidateRegistration $validateRegistration;
    private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        $this->magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->validateRegistration = new ValidateRegistration(
            $this->magicLinkTokenRepository,
            $this->userRepository
        );
    }

    public function testValidateWithValidToken(): void
    {
        $token = 'valid_token';
        $userId = 'user123';

        $magicLinkToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            $token,
            MagicLinkTokenType::REGISTRATION,
            $userId
        );

        $user = new User([], 'Test User', 'test@example.com', $userId);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($magicLinkToken);

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

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('delete')
            ->with($token);

        $result = $this->validateRegistration->validate($token);

        $this->assertTrue($result);
    }

    public function testValidateWithInvalidToken(): void
    {
        $token = 'invalid_token';

        $this->magicLinkTokenRepository->expects($this->once())
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

        $magicLinkToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            $token,
            MagicLinkTokenType::LOGIN,
            $userId
        );

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $result = $this->validateRegistration->validate($token);

        $this->assertFalse($result);
    }

    public function testValidateWithNonExistentUser(): void
    {
        $token = 'valid_token';
        $userId = 'non_existent_user';

        $magicLinkToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            $token,
            MagicLinkTokenType::REGISTRATION,
            $userId
        );

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($magicLinkToken);

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

        $magicLinkToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            $token,
            MagicLinkTokenType::REGISTRATION,
            $userId
        );

        $user = new User([], 'Test User', 'test@example.com', $userId, new \DateTime());

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $result = $this->validateRegistration->validate($token);

        $this->assertFalse($result);
    }
}
