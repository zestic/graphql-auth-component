<?php

declare(strict_types=1);

namespace Tests\Unit\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\Factory\TokenConfigFactory;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class TokenConfigFactoryTest extends TestCase
{
    public function testInvokeWithDefaultConfig(): void
    {
        // Create a container that returns an empty config
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn([]);

        $factory = new TokenConfigFactory();
        $tokenConfig = $factory($container);

        $this->assertInstanceOf(TokenConfig::class, $tokenConfig);
        $this->assertEquals(60, $tokenConfig->getAccessTokenTTLMinutes());
        $this->assertEquals(10, $tokenConfig->getLoginTTLMinutes());
        $this->assertEquals(10080, $tokenConfig->getRefreshTokenTTLMinutes());
        $this->assertEquals(1440, $tokenConfig->getRegistrationTTLMinutes());
    }

    public function testInvokeWithCustomConfig(): void
    {
        // Create a container that returns custom config values
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn([
                'auth' => [
                    'token' => [
                        'access_token_ttl' => 120,
                        'login_ttl' => 15,
                        'refresh_token_ttl' => 20160,
                        'registration_ttl' => 2880,
                    ],
                ],
            ]);

        $factory = new TokenConfigFactory();
        $tokenConfig = $factory($container);

        $this->assertInstanceOf(TokenConfig::class, $tokenConfig);
        $this->assertEquals(120, $tokenConfig->getAccessTokenTTLMinutes());
        $this->assertEquals(15, $tokenConfig->getLoginTTLMinutes());
        $this->assertEquals(20160, $tokenConfig->getRefreshTokenTTLMinutes());
        $this->assertEquals(2880, $tokenConfig->getRegistrationTTLMinutes());
    }

    public function testTokenConfigDateTimeGeneration(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn([
                'auth' => [
                    'token' => [
                        'access_token_ttl' => 60,
                        'login_ttl' => 10,
                        'refresh_token_ttl' => 10080,
                        'registration_ttl' => 1440,
                    ],
                ],
            ]);

        $factory = new TokenConfigFactory();
        $tokenConfig = $factory($container);

        // Test DateTime generation
        $now = new \DateTimeImmutable();

        $accessTokenExpiry = $tokenConfig->getAccessTokenTTLDateTime();
        $this->assertEqualsWithDelta(
            $now->modify('+60 minutes')->getTimestamp(),
            $accessTokenExpiry->getTimestamp(),
            5
        );

        $loginExpiry = $tokenConfig->getLoginTTLDateTime();
        $this->assertEqualsWithDelta(
            $now->modify('+10 minutes')->getTimestamp(),
            $loginExpiry->getTimestamp(),
            5
        );

        $refreshTokenExpiry = $tokenConfig->getRefreshTokenTTLDateTime();
        $this->assertEqualsWithDelta(
            $now->modify('+10080 minutes')->getTimestamp(),
            $refreshTokenExpiry->getTimestamp(),
            5
        );

        $registrationExpiry = $tokenConfig->getRegistrationTTLDateTime();
        $this->assertEqualsWithDelta(
            $now->modify('+1440 minutes')->getTimestamp(),
            $registrationExpiry->getTimestamp(),
            5
        );
    }
}
