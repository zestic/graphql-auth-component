<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\Factory\MagicLinkConfigFactory;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkConfig;

class MagicLinkConfigFactoryTest extends TestCase
{
    public function testInvokeWithCompleteConfig(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with('config')
            ->willReturn([
                'auth' => [
                    'magicLink' => [
                        'webAppUrl' => 'https://myapp.com',
                        'authCallbackPath' => '/custom/callback',
                        'magicLinkPath' => '/custom/magic-link',
                        'defaultSuccessMessage' => 'Custom success message',
                        'registrationSuccessMessage' => 'Custom registration message',
                    ],
                ],
            ]);

        $factory = new MagicLinkConfigFactory();
        $config = $factory($container);

        $this->assertInstanceOf(MagicLinkConfig::class, $config);
        $this->assertEquals('https://myapp.com', $config->webAppUrl);
        $this->assertEquals('/custom/callback', $config->authCallbackPath);
        $this->assertEquals('/custom/magic-link', $config->magicLinkPath);
        $this->assertEquals('Custom success message', $config->defaultSuccessMessage);
        $this->assertEquals('Custom registration message', $config->registrationSuccessMessage);
    }

    public function testConfigUrlGeneration(): void
    {
        $config = new MagicLinkConfig(
            webAppUrl: 'https://example.com',
            authCallbackPath: '/auth/callback',
            magicLinkPath: '/auth/magic-link',
            defaultSuccessMessage: 'Success',
            registrationSuccessMessage: 'Registration verified',
        );

        // Test URL generation methods
        $this->assertEquals('https://example.com/auth/callback', $config->getAuthCallbackUrl());
        $this->assertEquals('https://example.com/auth/magic-link', $config->getMagicLinkUrl());

        // Test URL generation with parameters
        $callbackUrl = $config->createAuthCallbackUrl(['token' => 'abc123', 'state' => 'xyz']);
        $this->assertEquals('https://example.com/auth/callback?token=abc123&state=xyz', $callbackUrl);

        $magicLinkUrl = $config->createMagicLinkUrl(['token' => 'def456']);
        $this->assertEquals('https://example.com/auth/magic-link?token=def456', $magicLinkUrl);

        // Test PKCE redirect URL
        $pkceUrl = $config->createPkceRedirectUrl('myapp://auth/callback', ['token' => 'ghi789']);
        $this->assertEquals('myapp://auth/callback?token=ghi789', $pkceUrl);
    }

    public function testBuildRedirectUrlWithExistingQuery(): void
    {
        $config = new MagicLinkConfig(
            webAppUrl: 'https://example.com',
            authCallbackPath: '/auth/callback',
            magicLinkPath: '/auth/magic-link',
            defaultSuccessMessage: 'Success',
            registrationSuccessMessage: 'Registration verified',
        );

        // Test with URL that already has query parameters
        $url = $config->buildRedirectUrl('https://example.com/path?existing=param', ['new' => 'value']);
        $this->assertEquals('https://example.com/path?existing=param&new=value', $url);

        // Test with URL that has no query parameters
        $url = $config->buildRedirectUrl('https://example.com/path', ['param' => 'value']);
        $this->assertEquals('https://example.com/path?param=value', $url);

        // Test with empty parameters
        $url = $config->buildRedirectUrl('https://example.com/path', []);
        $this->assertEquals('https://example.com/path', $url);
    }

    public function testWebAppUrlTrailingSlashHandling(): void
    {
        // Test with trailing slash
        $config = new MagicLinkConfig(
            webAppUrl: 'https://example.com/',
            authCallbackPath: '/auth/callback',
            magicLinkPath: '/auth/magic-link',
            defaultSuccessMessage: 'Success',
            registrationSuccessMessage: 'Registration verified',
        );

        $this->assertEquals('https://example.com/auth/callback', $config->getAuthCallbackUrl());
        $this->assertEquals('https://example.com/auth/magic-link', $config->getMagicLinkUrl());

        // Test without trailing slash
        $config2 = new MagicLinkConfig(
            webAppUrl: 'https://example.com',
            authCallbackPath: '/auth/callback',
            magicLinkPath: '/auth/magic-link',
            defaultSuccessMessage: 'Success',
            registrationSuccessMessage: 'Registration verified',
        );

        $this->assertEquals('https://example.com/auth/callback', $config2->getAuthCallbackUrl());
        $this->assertEquals('https://example.com/auth/magic-link', $config2->getMagicLinkUrl());
    }
}
