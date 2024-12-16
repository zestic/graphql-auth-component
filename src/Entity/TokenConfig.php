<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

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

    public function getLoginTTLMinutes(): int
    {
        return $this->loginTTLMinutes;
    }

    public function getRefreshTokenTTLMinutes(): int
    {
        return $this->refreshTokenTTLMinutes;
    }

    public function getRegistrationTTLMinutes(): int
    {
        return $this->registrationTTLMinutes;
    }
}
