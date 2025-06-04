<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ReissueExpiredMagicLinkToken
{
    public function __construct(
        private MagicLinkTokenFactory $magicLinkTokenFactory,
        private SendMagicLinkInterface $sendMagicLink,
        private SendVerificationLinkInterface $sendVerificationLink,
        private UserRepositoryInterface $userRepository,
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
    ) {
    }

    public function reissue(MagicLinkToken $expiredToken): array
    {
        try {
            // Get the user associated with the expired token
            $user = $this->userRepository->findUserById($expiredToken->getUserId());
            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'code' => 'USER_NOT_FOUND',
                ];
            }

            // Create a new token of the same type
            $newToken = match ($expiredToken->tokenType) {
                MagicLinkTokenType::LOGIN => $this->magicLinkTokenFactory->createLoginToken($expiredToken->getUserId()),
                MagicLinkTokenType::REGISTRATION => $this->magicLinkTokenFactory->createRegistrationToken($expiredToken->getUserId()),
            };

            // Send the new token via appropriate email service
            $sent = match ($expiredToken->tokenType) {
                MagicLinkTokenType::LOGIN => $this->sendMagicLink->send($newToken),
                MagicLinkTokenType::REGISTRATION => $this->sendRegistrationVerificationLink($user, $newToken),
            };

            if (! $sent) {
                return [
                    'success' => false,
                    'message' => 'Failed to send new magic link',
                    'code' => 'EMAIL_SEND_FAILED',
                ];
            }

            // Optionally delete the old expired token
            $this->magicLinkTokenRepository->delete($expiredToken);

            return [
                'success' => true,
                'message' => 'Token expired. A new magic link has been sent to your email.',
                'code' => 'TOKEN_EXPIRED_NEW_SENT',
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'message' => 'A system error occurred while reissuing the token',
                'code' => 'SYSTEM_ERROR',
            ];
        }
    }

    private function sendRegistrationVerificationLink(UserInterface $user, MagicLinkToken $token): bool
    {
        try {
            // Create a registration context from the user data
            $context = new RegistrationContext(
                $user->getEmail(),
                $user->additionalData ?? []
            );

            $this->sendVerificationLink->send($context, $token);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
