<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use Carbon\CarbonImmutable;

class MagicLinkToken
{
    public string $token;

    public function __construct(
        public readonly string $clientId,
        public readonly string $codeChallenge,
        public readonly string $codeChallengeMethod,
        public readonly string $redirectUri,
        public readonly string $state,
        public readonly string $email,
        public CarbonImmutable $expiration,
        public MagicLinkTokenType $tokenType,
        public string $userId,
        public ?string $ipAddress = null,
        public ?string $userAgent = null,
        public ?string $id = null,
    ) {
        $this->token = bin2hex(random_bytes(16));
    }

    public function getUserId(): string
    {
        return $this->userId;
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
        return $this->expiration->lessThan(CarbonImmutable::now());
    }
}
