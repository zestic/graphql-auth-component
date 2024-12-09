<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class EmailTokenConfig
{
    public function __construct(
        private int $loginTimeOfLifeMinutes,
        private int $registrationTimeOfLifeMinutes,
    )
    {
    }

    public function getLoginTimeOfLifeMinutes(): int
    {
        return $this->loginTimeOfLifeMinutes;
    }

    public function getRegistrationTimeOfLifeMinutes(): int
    {
        return $this->registrationTimeOfLifeMinutes;
    }
}
