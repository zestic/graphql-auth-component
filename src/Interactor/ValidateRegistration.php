<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ValidateRegistration
{
    public function __construct(
        private EmailTokenRepositoryInterface $emailTokenRepository,
        private UserRepositoryInterface $userRepository
    ) {
    }

    public function validate(string $token): bool
    {
        $emailToken = $this->emailTokenRepository->findByToken($token);
        if (!$emailToken) {
            return false;
        }

        if ($emailToken->tokenType !== EmailTokenType::REGISTRATION) {
            return false;
        }

        $user = $this->userRepository->findUserById($emailToken->userId);
        if (!$user) {
            return false;
        }

        if ($user->getVerifiedAt() !== null) {
            return false;
        }

        $user->setVerifiedAt(new \DateTime());
        $this->userRepository->update($user);
        $this->emailTokenRepository->delete($token);

        return true;
    }
}
