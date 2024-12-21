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

    public function testGetAccessTokenTTLDateTime(): void
    {
        $expected = (new \DateTime())->modify('+ 60 minutes');
        $this->assertEquals($expected->format('Y-m-d H:i:s'), $this->tokenConfig->getAccessTokenTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetAccessTokenTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 60 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getAccessTokenTTLDateTimeString());
    }

    public function testGetLoginTTLMinutes()
    {
        $this->assertEquals(30, $this->tokenConfig->getLoginTTLMinutes());
    }

    public function testGetLoginTTLDateTime(): void
    {
        $expected = (new \DateTime())->modify('+ 30 minutes');
        $this->assertEquals($expected->format('Y-m-d H:i:s'), $this->tokenConfig->getLoginTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetLoginTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 30 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getLoginTTLDateTimeString());
    }

    public function testGetRefreshTokenTTLMinutes()
    {
        $this->assertEquals(90, $this->tokenConfig->getRefreshTokenTTLMinutes());
    }

    public function testGetRefreshTokenTTLDateTime(): void
    {
        $expected = (new \DateTime())->modify('+ 90 minutes');
        $this->assertEquals($expected->format('Y-m-d H:i:s'), $this->tokenConfig->getRefreshTokenTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetRefreshTokenTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 90 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getRefreshTokenTTLDateTimeString());
    }

    public function testGetRegistrationTTLMinutes()
    {
        $this->assertEquals(120, $this->tokenConfig->getRegistrationTTLMinutes());
    }

    public function testGetRegistrationTTLDateTime(): void
    {
        $expected = (new \DateTime())->modify('+ 120 minutes');
        $this->assertEquals($expected->format('Y-m-d H:i:s'), $this->tokenConfig->getRegistrationTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetRegistrationTTLDateTimeString(): void
    {
        $expected = (new \DateTime())
            ->modify('+ 120 minutes')
            ->format('Y-m-d H:i:s');

        $this->assertEquals($expected, $this->tokenConfig->getRegistrationTTLDateTimeString());
    }
}
