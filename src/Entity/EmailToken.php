<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class EmailToken
{
    public function __construct(
        public \DateTimeInterface $expiration,
        public string $token,
        public EmailTokenType $tokenType,
        public string $userId,
        public ?string $id = null,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiration < new \DateTime();
    }
}
