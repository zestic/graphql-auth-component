<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use Carbon\CarbonImmutable;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ReissueExpiredMagicLinkTokenTest extends TestCase
{
    private ClientRepositoryInterface $clientRepository;

    private MagicLinkTokenFactory $magicLinkTokenFactory;

    private SendMagicLinkInterface $sendMagicLink;

    private SendVerificationLinkInterface $sendVerificationLink;

    private UserRepositoryInterface $userRepository;

    private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository;

    private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken;

    protected function setUp(): void
    {
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->magicLinkTokenFactory = $this->createMock(MagicLinkTokenFactory::class);
        $this->sendMagicLink = $this->createMock(SendMagicLinkInterface::class);
        $this->sendVerificationLink = $this->createMock(SendVerificationLinkInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);

        // Mock the client repository to return a client entity
        $mockClient = $this->createMock(ClientEntity::class);
        $this->clientRepository->method('getClientEntity')->willReturn($mockClient);

        $this->reissueExpiredMagicLinkToken = new ReissueExpiredMagicLinkToken(
            $this->clientRepository,
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
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('-1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $expiredToken->token = 'expired_token';

        $user = new User([], 'Test User', 'test@example.com', 'user123');
        $newToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $newToken->token = 'new_token';

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user123')
            ->willReturn($user);

        $this->magicLinkTokenFactory->expects($this->once())
            ->method('createLoginToken')
            ->with('user123', $this->anything(), $this->anything())
            ->willReturn($newToken);

        $this->sendMagicLink->expects($this->once())
            ->method('send')
            ->with($newToken);

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
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('-1 hour'),
            tokenType: MagicLinkTokenType::REGISTRATION,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $expiredToken->token = 'expired_token';

        $user = new User(['displayName' => 'Test User'], 'Test User', 'test@example.com', 'user123');
        $newToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::REGISTRATION,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $newToken->token = 'new_token';

        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user123')
            ->willReturn($user);

        $this->magicLinkTokenFactory->expects($this->once())
            ->method('createRegistrationToken')
            ->with('user123', $this->anything(), $this->anything())
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
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('-1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'nonexistent_user'
        );
        // Override the generated token with the expected test token
        $expiredToken->token = 'expired_token';

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
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('-1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $expiredToken->token = 'expired_token';

        $user = new User([], 'Test User', 'test@example.com', 'user123');
        $newToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('+1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $newToken->token = 'new_token';

        $this->userRepository->method('findUserById')->willReturn($user);
        $this->magicLinkTokenFactory->method('createLoginToken')->willReturn($newToken);
        $this->sendMagicLink->method('send')->willThrowException(new \Exception('Email failed'));

        $result = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);

        $this->assertEquals([
            'success' => false,
            'message' => 'A system error occurred while reissuing the token',
            'code' => 'SYSTEM_ERROR',
        ], $result);
    }

    public function testReissueWithException(): void
    {
        $expiredToken = new MagicLinkToken(
            clientId: 'test-client',
            codeChallenge: 'test-challenge',
            codeChallengeMethod: 'S256',
            redirectUri: 'https://example.com/callback',
            state: 'test-state',
            email: 'test@example.com',
            expiration: CarbonImmutable::parse('-1 hour'),
            tokenType: MagicLinkTokenType::LOGIN,
            userId: 'user123'
        );
        // Override the generated token with the expected test token
        $expiredToken->token = 'expired_token';

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
