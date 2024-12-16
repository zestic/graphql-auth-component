<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkCommunicationInterface;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\SendMagicLink;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class SendMagicLinkTest extends TestCase
{
    private UserRepositoryInterface $userRepository;
    private EmailTokenFactory $emailTokenFactory;
    private SendMagicLinkCommunicationInterface $communication;
    private SendMagicLink $sendMagicLink;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->emailTokenFactory = $this->createMock(EmailTokenFactory::class);
        $this->communication = $this->createMock(SendMagicLinkCommunicationInterface::class);

        $this->sendMagicLink = new SendMagicLink(
            $this->emailTokenFactory,
            $this->communication,
            $this->userRepository,
        );
    }

    public function testSendSuccessWhenUserExists(): void
    {
        $email = 'test@example.com';
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');

        $emailToken = $this->createMock(EmailToken::class);

        $this->userRepository->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn($user);

        $this->emailTokenFactory->expects($this->once())
            ->method('createLoginToken')
            ->with('user-id')
            ->willReturn($emailToken);

        $this->communication->expects($this->once())
            ->method('send')
            ->with($emailToken)
            ->willReturn(true);

        $result = $this->sendMagicLink->send($email);

        $this->assertEquals([
            'success' => true,
            'message' => 'Success',
            'code' => 'MAGIC_LINK_SUCCESS',
        ], $result);
    }

    public function testSendSuccessWhenUserDoesNotExist(): void
    {
        $email = 'nonexistent@example.com';

        $this->userRepository->expects($this->once())
            ->method('findUserByEmail')
            ->with($email)
            ->willReturn(null);

        $this->emailTokenFactory->expects($this->never())->method('createLoginToken');
        $this->communication->expects($this->never())->method('send');

        $result = $this->sendMagicLink->send($email);

        $this->assertEquals([
            'success' => true,
            'message' => 'Success',
            'code' => 'MAGIC_LINK_SUCCESS',
        ], $result);
    }

    public function testSendFailureWhenCommunicationFails(): void
    {
        $email = 'test@example.com';
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');

        $emailToken = $this->createMock(EmailToken::class);

        $this->userRepository->method('findUserByEmail')->willReturn($user);
        $this->emailTokenFactory->method('createLoginToken')->willReturn($emailToken);
        $this->communication->method('send')->willReturn(false);

        $result = $this->sendMagicLink->send($email);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }

    public function testSendFailureWhenExceptionOccurs(): void
    {
        $email = 'test@example.com';

        $this->userRepository->method('findUserByEmail')
            ->willThrowException(new \RuntimeException('Database error'));

        $result = $this->sendMagicLink->send($email);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }
}
