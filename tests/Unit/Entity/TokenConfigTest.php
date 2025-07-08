<?php

namespace Tests\Unit\Entity;

use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class TokenConfigTest extends TestCase
{
    private TokenConfig $tokenConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // Set Carbon test time to a fixed point for consistent testing
        CarbonImmutable::setTestNow('2024-06-05 00:00:00');

        $this->tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 60,
            loginTTLMinutes: 30,
            refreshTokenTTLMinutes: 90,
            registrationTTLMinutes: 120
        );
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function testGetAccessTokenTTLMinutes(): void
    {
        $this->assertEquals(60, $this->tokenConfig->getAccessTokenTTLMinutes());
    }

    public function testGetAccessTokenTTLDateTime(): void
    {
        $this->assertEquals('2024-06-05 01:00:00', $this->tokenConfig->getAccessTokenTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetAccessTokenTTLDateTimeString(): void
    {
        $this->assertEquals('2024-06-05 01:00:00', $this->tokenConfig->getAccessTokenTTLDateTimeString());
    }

    public function testGetLoginTTLMinutes()
    {
        $this->assertEquals(30, $this->tokenConfig->getLoginTTLMinutes());
    }

    public function testGetLoginTTLDateTime(): void
    {
        $this->assertEquals('2024-06-05 00:30:00', $this->tokenConfig->getLoginTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetLoginTTLDateTimeString(): void
    {
        $this->assertEquals('2024-06-05 00:30:00', $this->tokenConfig->getLoginTTLDateTimeString());
    }

    public function testGetRefreshTokenTTLMinutes()
    {
        $this->assertEquals(90, $this->tokenConfig->getRefreshTokenTTLMinutes());
    }

    public function testGetRefreshTokenTTLDateTime(): void
    {
        $this->assertEquals('2024-06-05 01:30:00', $this->tokenConfig->getRefreshTokenTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetRefreshTokenTTLDateTimeString(): void
    {
        $this->assertEquals('2024-06-05 01:30:00', $this->tokenConfig->getRefreshTokenTTLDateTimeString());
    }

    public function testGetRegistrationTTLMinutes()
    {
        $this->assertEquals(120, $this->tokenConfig->getRegistrationTTLMinutes());
    }

    public function testGetRegistrationTTLDateTime(): void
    {
        $this->assertEquals('2024-06-05 02:00:00', $this->tokenConfig->getRegistrationTTLDateTime()->format('Y-m-d H:i:s'));
    }

    public function testGetRegistrationTTLDateTimeString(): void
    {
        $this->assertEquals('2024-06-05 02:00:00', $this->tokenConfig->getRegistrationTTLDateTimeString());
    }
}
