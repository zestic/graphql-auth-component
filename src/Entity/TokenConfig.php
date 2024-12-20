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

    public function getAccessTokenTTLTimestamp(): int
    {
        return (new DateTime("+ $this->accessTokenTTLMinutes minutes"))->getTimestamp();
    }

    public function getLoginTTLMinutes(): int
    {
        return $this->loginTTLMinutes;
    }

    public function getLoginTTLTimestamp(): int
    {
        return (new DateTime("+ $this->loginTTLMinutes minutes"))->getTimestamp();
    }

    public function getRefreshTokenTTLMinutes(): int
    {
        return $this->refreshTokenTTLMinutes;
    }

    public function getRefreshTokenTTLTimestamp(): int
    {
        return (new DateTime("+ $this->refreshTokenTTLMinutes minutes"))->getTimestamp();
    }

    public function getRegistrationTTLMinutes(): int
    {
        return $this->registrationTTLMinutes;
    }

    public function getRegistrationTTLTimestamp(): int
    {
        return (new DateTime("+ $this->registrationTTLMinutes minutes"))->getTimestamp();
    }
}
