<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class EmailToken
{
    public function __construct(
        public \DateTimeInterface $expirationTime,
        public string $token,
        public EmailTokenType $tokenType,
        public array $userAgent,
        public string $userId,
    ) {
    }
}
