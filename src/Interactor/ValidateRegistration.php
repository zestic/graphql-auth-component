<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ValidateRegistration
{
    public function __construct(
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
        private UserRepositoryInterface $userRepository,
        private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken,
    ) {
    }

    public function validate(string $token): array
    {
        $magicLinkToken = $this->magicLinkTokenRepository->findByUnexpiredToken($token);
        if (! $magicLinkToken) {
            // Check if token exists but is expired
            $expiredToken = $this->magicLinkTokenRepository->findByToken($token);
            if ($expiredToken && $expiredToken->isExpired() && $expiredToken->tokenType === MagicLinkTokenType::REGISTRATION) {
                // Reissue the expired registration token
                return $this->reissueExpiredMagicLinkToken->reissue($expiredToken);
            }

            return [
                'success' => false,
                'message' => 'Invalid token',
                'code' => 'INVALID_TOKEN',
            ];
        }

        if ($magicLinkToken->tokenType !== MagicLinkTokenType::REGISTRATION) {
            return [
                'success' => false,
                'message' => 'Invalid token type',
                'code' => 'INVALID_TOKEN_TYPE',
            ];
        }

        $user = $this->userRepository->findUserById($magicLinkToken->userId);
        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
                'code' => 'USER_NOT_FOUND',
            ];
        }

        if ($user->getVerifiedAt() !== null) {
            return [
                'success' => false,
                'message' => 'User already verified',
                'code' => 'USER_ALREADY_VERIFIED',
            ];
        }

        try {
            $user->setVerifiedAt(new \DateTime());
            $this->userRepository->update($user);
            $this->magicLinkTokenRepository->delete($token);

            return [
                'success' => true,
                'message' => 'Registration validated successfully',
                'code' => 'REGISTRATION_VALIDATED',
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'message' => 'A system error occurred',
                'code' => 'SYSTEM_ERROR',
            ];
        }
    }
}
