<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class MagicLinkToken
{
    public function __construct(
        public \DateTimeInterface $expiration,
        public string $token,
        public MagicLinkTokenType $tokenType,
        public string $userId,
        public ?string $id = null,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function isExpired(): bool
    {
        return $this->expiration < new \DateTime();
    }
}
