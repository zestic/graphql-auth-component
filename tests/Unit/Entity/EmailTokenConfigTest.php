<?php

namespace Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenConfig;

class EmailTokenConfigTest extends TestCase
{
    public function testGetLoginTimeOfLifeMinutes()
    {
        $config = new EmailTokenConfig(30, 60);
        $this->assertEquals(30, $config->getLoginTimeOfLifeMinutes());
    }

    public function testGetRegistrationTimeOfLifeMinutes()
    {
        $config = new EmailTokenConfig(30, 60);
        $this->assertEquals(60, $config->getRegistrationTimeOfLifeMinutes());
    }
}
