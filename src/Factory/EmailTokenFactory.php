<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Factory;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenConfig;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class EmailTokenFactory
{
    public function __construct(
        private EmailTokenConfig $config,
        private EmailTokenRepositoryInterface $emailTokenRepository,
    ) {
    }

    public function createRegistrationToken(string $userId): EmailToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getRegistrationTimeOfLifeMinutes()} minutes");

        $token = new EmailToken(
            $expiration,
            bin2hex(random_bytes(16)),
            EmailTokenType::REGISTRATION,
            $userId,
        );

        if ($this->emailTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create email token');
    }

    public function createLoginToken(string $userId): EmailToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getLoginTimeOfLifeMinutes()} minutes");

        $token = new EmailToken(
            $expiration,
            bin2hex(random_bytes(16)),
            EmailTokenType::LOGIN,
            $userId,
        );

        if ($this->emailTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create email token');
    }
}
