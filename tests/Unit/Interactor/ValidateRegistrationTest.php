<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Interactor\ValidateRegistration;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ValidateRegistrationTest extends TestCase
{
    private ValidateRegistration $validateRegistration;

    private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository;

    private UserRepositoryInterface $userRepository;

    private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken;

    protected function setUp(): void
    {
        $this->magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->reissueExpiredMagicLinkToken = $this->createMock(ReissueExpiredMagicLinkToken::class);

        $this->validateRegistration = new ValidateRegistration(
            $this->magicLinkTokenRepository,
            $this->userRepository,
            $this->reissueExpiredMagicLinkToken
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
            ->method('findByUnexpiredToken')
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

        $this->assertEquals([
            'success' => true,
            'message' => 'Registration validated successfully',
            'code' => 'REGISTRATION_VALIDATED',
        ], $result);
    }

    public function testValidateWithExpiredToken(): void
    {
        $token = 'expired_token';
        $expiredToken = new MagicLinkToken(
            new \DateTime('-1 hour'),
            $token,
            MagicLinkTokenType::REGISTRATION,
            'user123'
        );

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn(null);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($expiredToken);

        $reissueResponse = [
            'success' => true,
            'message' => 'Token expired. A new magic link has been sent to your email.',
            'code' => 'TOKEN_EXPIRED_NEW_SENT',
        ];

        $this->reissueExpiredMagicLinkToken->expects($this->once())
            ->method('reissue')
            ->with($expiredToken)
            ->willReturn($reissueResponse);

        $result = $this->validateRegistration->validate($token);

        $this->assertEquals($reissueResponse, $result);
    }

    public function testValidateWithInvalidToken(): void
    {
        $token = 'invalid_token';

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn(null);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn(null);

        $result = $this->validateRegistration->validate($token);

        $this->assertEquals([
            'success' => false,
            'message' => 'Invalid token',
            'code' => 'INVALID_TOKEN',
        ], $result);
    }

    public function testValidateWithWrongTokenType(): void
    {
        $token = 'login_token';
        $magicLinkToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            $token,
            MagicLinkTokenType::LOGIN,
            'user123'
        );

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $result = $this->validateRegistration->validate($token);

        $this->assertEquals([
            'success' => false,
            'message' => 'Invalid token type',
            'code' => 'INVALID_TOKEN_TYPE',
        ], $result);
    }

    public function testValidateWithUserNotFound(): void
    {
        $token = 'valid_token';
        $magicLinkToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            $token,
            MagicLinkTokenType::REGISTRATION,
            'nonexistent_user'
        );

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('nonexistent_user')
            ->willReturn(null);

        $result = $this->validateRegistration->validate($token);

        $this->assertEquals([
            'success' => false,
            'message' => 'User not found',
            'code' => 'USER_NOT_FOUND',
        ], $result);
    }

    public function testValidateWithUserAlreadyVerified(): void
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
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $result = $this->validateRegistration->validate($token);

        $this->assertEquals([
            'success' => false,
            'message' => 'User already verified',
            'code' => 'USER_ALREADY_VERIFIED',
        ], $result);
    }

    public function testValidateWithSystemError(): void
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
            ->method('findByUnexpiredToken')
            ->with($token)
            ->willReturn($magicLinkToken);

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with($userId)
            ->willReturn($user);

        $this->userRepository->expects($this->once())
            ->method('update')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->validateRegistration->validate($token);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }
}
