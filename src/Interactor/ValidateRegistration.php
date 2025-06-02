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
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function validate(string $token): bool
    {
        $magicLinkToken = $this->magicLinkTokenRepository->findByToken($token);
        if (!$magicLinkToken) {
            return false;
        }

        if ($magicLinkToken->tokenType !== MagicLinkTokenType::REGISTRATION) {
            return false;
        }

        $user = $this->userRepository->findUserById($magicLinkToken->userId);
        if (!$user) {
            return false;
        }

        if ($user->getVerifiedAt() !== null) {
            return false;
        }

        $user->setVerifiedAt(new \DateTime());
        $this->userRepository->update($user);
        $this->magicLinkTokenRepository->delete($token);

        return true;
    }
}
