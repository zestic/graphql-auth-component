<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\SendMagicLink;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class SendMagicLinkTest extends TestCase
{
    private UserRepositoryInterface $userRepository;

    private MagicLinkTokenFactory $magicLinkTokenFactory;

    private SendMagicLinkInterface $email;

    private SendMagicLink $sendMagicLink;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->magicLinkTokenFactory = $this->createMock(MagicLinkTokenFactory::class);
        $this->email = $this->createMock(SendMagicLinkInterface::class);

        $this->sendMagicLink = new SendMagicLink(
            $this->magicLinkTokenFactory,
            $this->email,
            $this->userRepository,
        );
    }

    public function testSendSuccessWhenUserExists(): void
    {
        $email = 'test@example.com';
        $context = MagicLinkContext::simple($email);
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');

        $magicLinkToken = $this->createMock(MagicLinkToken::class);

        $this->userRepository->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn($user);

        $this->magicLinkTokenFactory->expects($this->once())
            ->method('createLoginTokenWithContext')
            ->with('user-id', $context)
            ->willReturn($magicLinkToken);

        $this->email->expects($this->once())
            ->method('send')
            ->with($magicLinkToken);

        $result = $this->sendMagicLink->send($context);

        $this->assertEquals([
            'success' => true,
            'message' => 'Success',
            'code' => 'MAGIC_LINK_SUCCESS',
        ], $result);
    }

    public function testSendSuccessWhenUserDoesNotExist(): void
    {
        $email = 'nonexistent@example.com';
        $context = MagicLinkContext::simple($email);

        $this->userRepository->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn(null);

        $this->magicLinkTokenFactory->expects($this->never())->method('createLoginTokenWithContext');
        $this->email->expects($this->never())->method('send');

        $result = $this->sendMagicLink->send($context);

        $this->assertEquals([
            'success' => true,
            'message' => 'Success',
            'code' => 'MAGIC_LINK_SUCCESS',
        ], $result);
    }

    public function testSendFailureWhenCommunicationFails(): void
    {
        $email = 'test@example.com';
        $context = MagicLinkContext::simple($email);
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');

        $magicLinkToken = $this->createMock(MagicLinkToken::class);

        $this->userRepository->method('findUserByEmail')->willReturn($user);
        $this->magicLinkTokenFactory->method('createLoginTokenWithContext')->willReturn($magicLinkToken);
        $this->email->method('send')->willThrowException(new \Exception('Email failed'));

        $result = $this->sendMagicLink->send($context);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }

    public function testSendFailureWhenExceptionOccurs(): void
    {
        $email = 'test@example.com';
        $context = MagicLinkContext::simple($email);

        $this->userRepository->method('findUserByEmail')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->sendMagicLink->send($context);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }
}
