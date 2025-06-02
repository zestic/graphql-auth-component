<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use DateTimeImmutable;

class TokenConfig
{
    public function __construct(
        private int $accessTokenTTLMinutes,
        private int $loginTTLMinutes,
        private int $refreshTokenTTLMinutes,
        private int $registrationTTLMinutes,
    ) {
    }

    public function getAccessTokenTTLMinutes(): int
    {
        return $this->accessTokenTTLMinutes;
    }

    public function getAccessTokenTTLDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable("+ $this->accessTokenTTLMinutes minutes");
    }

    public function getAccessTokenTTLDateTimeString(): string
    {
        return $this->getAccessTokenTTLDateTime()->format('Y-m-d H:i:s');
    }

    public function getLoginTTLMinutes(): int
    {
        return $this->loginTTLMinutes;
    }

    public function getLoginTTLDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable("+ $this->loginTTLMinutes minutes");
    }

    public function getLoginTTLDateTimeString(): string
    {
        return $this->getLoginTTLDateTime()->format('Y-m-d H:i:s');
    }

    public function getRefreshTokenTTLMinutes(): int
    {
        return $this->refreshTokenTTLMinutes;
    }

    public function getRefreshTokenTTLDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable("+ $this->refreshTokenTTLMinutes minutes");
    }

    public function getRefreshTokenTTLDateTimeString(): string
    {
        return $this->getRefreshTokenTTLDateTime()->format('Y-m-d H:i:s');
    }

    public function getRegistrationTTLMinutes(): int
    {
        return $this->registrationTTLMinutes;
    }

    public function getRegistrationTTLDateTime(): DateTimeImmutable
    {
        return new DateTimeImmutable("+ $this->registrationTTLMinutes minutes");
    }

    public function getRegistrationTTLDateTimeString(): string
    {
        return $this->getRegistrationTTLDateTime()->format('Y-m-d H:i:s');
    }
}
