<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ReissueExpiredMagicLinkTokenTest extends TestCase
{
    private MagicLinkTokenFactory $magicLinkTokenFactory;

    private SendMagicLinkInterface $sendMagicLink;

    private SendVerificationLinkInterface $sendVerificationLink;

    private UserRepositoryInterface $userRepository;

    private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository;

    private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken;

    protected function setUp(): void
    {
        $this->magicLinkTokenFactory = $this->createMock(MagicLinkTokenFactory::class);
        $this->sendMagicLink = $this->createMock(SendMagicLinkInterface::class);
        $this->sendVerificationLink = $this->createMock(SendVerificationLinkInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);

        $this->reissueExpiredMagicLinkToken = new ReissueExpiredMagicLinkToken(
            $this->magicLinkTokenFactory,
            $this->sendMagicLink,
            $this->sendVerificationLink,
            $this->userRepository,
            $this->magicLinkTokenRepository,
        );
    }

    public function testReissueExpiredLoginToken(): void
    {
        $expiredToken = new MagicLinkToken(
            new \DateTime('-1 hour'),
            'expired_token',
            MagicLinkTokenType::LOGIN,
            'user123'
        );

        $user = new User([], 'Test User', 'test@example.com', 'user123');
        $newToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            'new_token',
            MagicLinkTokenType::LOGIN,
            'user123'
        );

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user123')
            ->willReturn($user);

        $this->magicLinkTokenFactory->expects($this->once())
            ->method('createLoginToken')
            ->with('user123')
            ->willReturn($newToken);

        $this->sendMagicLink->expects($this->once())
            ->method('send')
            ->with($newToken)
            ->willReturn(true);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('delete')
            ->with($expiredToken);

        $result = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);

        $this->assertEquals([
            'success' => true,
            'message' => 'Token expired. A new magic link has been sent to your email.',
            'code' => 'TOKEN_EXPIRED_NEW_SENT',
        ], $result);
    }

    public function testReissueExpiredRegistrationToken(): void
    {
        $expiredToken = new MagicLinkToken(
            new \DateTime('-1 hour'),
            'expired_token',
            MagicLinkTokenType::REGISTRATION,
            'user123'
        );

        $user = new User(['displayName' => 'Test User'], 'Test User', 'test@example.com', 'user123');
        $newToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            'new_token',
            MagicLinkTokenType::REGISTRATION,
            'user123'
        );

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user123')
            ->willReturn($user);

        $this->magicLinkTokenFactory->expects($this->once())
            ->method('createRegistrationToken')
            ->with('user123')
            ->willReturn($newToken);

        $this->sendVerificationLink->expects($this->once())
            ->method('send')
            ->with($this->anything(), $newToken);

        $this->magicLinkTokenRepository->expects($this->once())
            ->method('delete')
            ->with($expiredToken);

        $result = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);

        $this->assertEquals([
            'success' => true,
            'message' => 'Token expired. A new magic link has been sent to your email.',
            'code' => 'TOKEN_EXPIRED_NEW_SENT',
        ], $result);
    }

    public function testReissueWithUserNotFound(): void
    {
        $expiredToken = new MagicLinkToken(
            new \DateTime('-1 hour'),
            'expired_token',
            MagicLinkTokenType::LOGIN,
            'nonexistent_user'
        );

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('nonexistent_user')
            ->willReturn(null);

        $result = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);

        $this->assertEquals([
            'success' => false,
            'message' => 'User not found',
            'code' => 'USER_NOT_FOUND',
        ], $result);
    }

    public function testReissueWithEmailSendFailure(): void
    {
        $expiredToken = new MagicLinkToken(
            new \DateTime('-1 hour'),
            'expired_token',
            MagicLinkTokenType::LOGIN,
            'user123'
        );

        $user = new User([], 'Test User', 'test@example.com', 'user123');
        $newToken = new MagicLinkToken(
            new \DateTime('+1 hour'),
            'new_token',
            MagicLinkTokenType::LOGIN,
            'user123'
        );

        $this->userRepository->method('findUserById')->willReturn($user);
        $this->magicLinkTokenFactory->method('createLoginToken')->willReturn($newToken);
        $this->sendMagicLink->method('send')->willReturn(false);

        $result = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);

        $this->assertEquals([
            'success' => false,
            'message' => 'Failed to send new magic link',
            'code' => 'EMAIL_SEND_FAILED',
        ], $result);
    }

    public function testReissueWithException(): void
    {
        $expiredToken = new MagicLinkToken(
            new \DateTime('-1 hour'),
            'expired_token',
            MagicLinkTokenType::LOGIN,
            'user123'
        );

        $this->userRepository->method('findUserById')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred while reissuing the token',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }
}
