<?php

namespace Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use SlopeIt\ClockMock\ClockMock;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class TokenConfigTest extends TestCase
{
    private TokenConfig $tokenConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 60,
            loginTTLMinutes: 30,
            refreshTokenTTLMinutes: 90,
            registrationTTLMinutes: 120
        );
        ClockMock::freeze(new \DateTime('2024-06-05'));
    }

    protected function tearDown(): void
    {
        ClockMock::reset();
        parent::tearDown();
    }

    public function testGetAccessTokenTTLMinutes(): void
    {
        $this->assertEquals(60, $this->tokenConfig->getAccessTokenTTLMinutes());
    }

    public function testGetAccessTokenTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 60 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getAccessTokenTTLDateTimeString());
    }

    public function testGetLoginTimeOfLifeMinutes()
    {
        $this->assertEquals(30, $this->tokenConfig->getLoginTTLMinutes());
    }

    public function testGetLoginTTLTimestamp(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 30 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getLoginTTLDateTimeString());
    }

    public function testGetRefreshTokenTimeOfLifeMinutes()
    {
        $this->assertEquals(90, $this->tokenConfig->getRefreshTokenTTLMinutes());
    }

    public function testGetRefreshTokenTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 90 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getRefreshTokenTTLDateTimeString());
    }

    public function testGetRegistrationTimeOfLifeMinutes()
    {
        $this->assertEquals(120, $this->tokenConfig->getRegistrationTTLMinutes());
    }

    public function testGetRegistrationTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 120 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getRegistrationTTLDateTimeString());
    }
}
