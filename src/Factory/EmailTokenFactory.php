<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Factory;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class EmailTokenFactory
{
    public function __construct(
        private TokenConfig $config,
        private EmailTokenRepositoryInterface $emailTokenRepository,
    ) {
    }

    public function createLoginToken(string|int $userId): EmailToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getLoginTTLMinutes()} minutes");

        $token = new EmailToken(
            $expiration,
            bin2hex(random_bytes(16)),
            EmailTokenType::LOGIN,
            (string)$userId,
        );

        if ($this->emailTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create email token');
    }

    public function createRegistrationToken(string|int $userId): EmailToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getRegistrationTTLMinutes()} minutes");

        $token = new EmailToken(
            $expiration,
            bin2hex(random_bytes(16)),
            EmailTokenType::REGISTRATION,
            (string)$userId,
        );

        if ($this->emailTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create email token');
    }
}
