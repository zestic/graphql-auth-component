<?php

namespace Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class TokenConfigTest extends TestCase
{

    public function testGetAccessTokenTimeOfLifeMinutes()
    {
        $config = new TokenConfig(30, 60, 90, 120);
        $this->assertEquals(30, $config->getAccessTokenTTLMinutes());
    }

    public function testGetLoginTimeOfLifeMinutes()
    {
        $config = new TokenConfig(30, 60, 90, 120);
        $this->assertEquals(60, $config->getLoginTTLMinutes());
    }

    public function testGetRefreshTokenTimeOfLifeMinutes()
    {
        $config = new TokenConfig(30, 60, 90, 120);
        $this->assertEquals(90, $config->getRefreshTokenTTLMinutes());
    }

    public function testGetRegistrationTimeOfLifeMinutes()
    {
        $config = new TokenConfig(30, 60, 90, 120);
        $this->assertEquals(120, $config->getRegistrationTTLMinutes());
    }
}
