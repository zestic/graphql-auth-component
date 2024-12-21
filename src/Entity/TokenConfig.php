<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use DateTime;

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

    public function getAccessTokenTTLDateTimeString(): string
    {
        return (new DateTime("+ $this->accessTokenTTLMinutes minutes"))->format('Y-m-d H:i:s');
    }

    public function getLoginTTLMinutes(): int
    {
        return $this->loginTTLMinutes;
    }

    public function getLoginTTLDateTimeString(): string
    {
        return (new DateTime("+ $this->loginTTLMinutes minutes"))->format('Y-m-d H:i:s');
    }

    public function getRefreshTokenTTLMinutes(): int
    {
        return $this->refreshTokenTTLMinutes;
    }

    public function getRefreshTokenTTLDateTimeString(): string
    {
        return (new DateTime("+ $this->refreshTokenTTLMinutes minutes"))->format('Y-m-d H:i:s');
    }

    public function getRegistrationTTLMinutes(): int
    {
        return $this->registrationTTLMinutes;
    }

    public function getRegistrationTTLDateTimeString(): string
    {
        return (new DateTime("+ $this->registrationTTLMinutes minutes"))->format('Y-m-d H:i:s');
    }
}
