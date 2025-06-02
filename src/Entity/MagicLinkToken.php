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
        public ?string $payload = null,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $id = null,
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getPayload(): ?string
    {
        return $this->payload;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function isExpired(): bool
    {
        return $this->expiration < new \DateTime();
    }
}
