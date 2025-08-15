<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Factory;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthorizationServerFactory;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class AuthorizationServerFactoryTest extends TestCase
{
    private ContainerInterface $container;

    private AuthorizationServerFactory $factory;

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new AuthorizationServerFactory();
    }

    public function testSuccessfulAuthorizationServerCreation()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => null,
                ],
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        $tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 60,
            loginTTLMinutes: 10,
            refreshTokenTTLMinutes: 10080,
            registrationTTLMinutes: 1440
        );

        // Mock all required repositories
        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $authCodeRepository = $this->createMock(AuthCodeRepositoryInterface::class);
        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);

        $this->container
            ->method('get')
            ->willReturnMap([
                ['config', $config],
                [ClientRepositoryInterface::class, $clientRepository],
                [AccessTokenRepositoryInterface::class, $accessTokenRepository],
                [ScopeRepositoryInterface::class, $scopeRepository],
                [AuthCodeRepositoryInterface::class, $authCodeRepository],
                [RefreshTokenRepositoryInterface::class, $refreshTokenRepository],
                [MagicLinkTokenRepositoryInterface::class, $magicLinkTokenRepository],
                [UserRepositoryInterface::class, $userRepository],
                [TokenConfig::class, $tokenConfig],
            ]);

        $server = ($this->factory)($this->container);

        $this->assertInstanceOf(AuthorizationServer::class, $server);
    }

    public function testWithPassphrase()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => 'test-passphrase',
                ],
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        $tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 120,
            loginTTLMinutes: 15,
            refreshTokenTTLMinutes: 20160,
            registrationTTLMinutes: 2880
        );

        $this->setupMockRepositories($tokenConfig, $config);

        $server = ($this->factory)($this->container);

        $this->assertInstanceOf(AuthorizationServer::class, $server);
    }

    public function testMissingAuthConfiguration()
    {
        $config = []; // No auth configuration

        $this->container
            ->method('get')
            ->with('config')
            ->willReturn($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Auth configuration not found');

        ($this->factory)($this->container);
    }

    public function testMissingPrivateKeyPath()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    // Missing privateKeyPath
                    'passphrase' => null,
                ],
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        $this->container
            ->method('get')
            ->with('config')
            ->willReturn($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Private key path not configured');

        ($this->factory)($this->container);
    }

    public function testMissingEncryptionKey()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => null,
                ],
                // Missing encryptionKey
            ],
        ];

        $this->container
            ->method('get')
            ->with('config')
            ->willReturn($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Encryption key not configured');

        ($this->factory)($this->container);
    }

    public function testWithDifferentTokenTTLValues()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => null,
                ],
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        // Test with different TTL values
        $tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 30,    // 30 minutes
            loginTTLMinutes: 5,           // 5 minutes
            refreshTokenTTLMinutes: 43200, // 30 days
            registrationTTLMinutes: 720   // 12 hours
        );

        $this->setupMockRepositories($tokenConfig, $config);

        $server = ($this->factory)($this->container);

        $this->assertInstanceOf(AuthorizationServer::class, $server);
    }

    public function testWithNullPassphrase()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => null,
                ],
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        $tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 60,
            loginTTLMinutes: 10,
            refreshTokenTTLMinutes: 10080,
            registrationTTLMinutes: 1440
        );

        $this->setupMockRepositories($tokenConfig, $config);

        $server = ($this->factory)($this->container);

        $this->assertInstanceOf(AuthorizationServer::class, $server);
    }

    public function testWithEmptyPassphrase()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => '',
                ],
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        $tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 60,
            loginTTLMinutes: 10,
            refreshTokenTTLMinutes: 10080,
            registrationTTLMinutes: 1440
        );

        $this->setupMockRepositories($tokenConfig, $config);

        $server = ($this->factory)($this->container);

        $this->assertInstanceOf(AuthorizationServer::class, $server);
    }

    public function testFactoryIsCallable()
    {
        $this->assertTrue(is_callable($this->factory));
    }

    public function testMissingJwtConfiguration()
    {
        $config = [
            'auth' => [
                // Missing jwt configuration
                'encryptionKey' => 'test-encryption-key-32-characters',
            ],
        ];

        $this->container
            ->method('get')
            ->with('config')
            ->willReturn($config);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Private key path not configured');

        ($this->factory)($this->container);
    }

    public function testWithLongEncryptionKey()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => __DIR__ . '/../../../../config/jwt/private.key',
                    'passphrase' => 'complex-passphrase-with-special-chars!@#$%',
                ],
                'encryptionKey' => 'very-long-encryption-key-with-64-characters-for-maximum-security',
            ],
        ];

        $tokenConfig = new TokenConfig(
            accessTokenTTLMinutes: 60,
            loginTTLMinutes: 10,
            refreshTokenTTLMinutes: 10080,
            registrationTTLMinutes: 1440
        );

        $this->setupMockRepositories($tokenConfig, $config);

        $server = ($this->factory)($this->container);

        $this->assertInstanceOf(AuthorizationServer::class, $server);
    }

    private function setupMockRepositories(TokenConfig $tokenConfig, array $config): void
    {
        $clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $scopeRepository = $this->createMock(ScopeRepositoryInterface::class);
        $authCodeRepository = $this->createMock(AuthCodeRepositoryInterface::class);
        $refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);

        $this->container
            ->method('get')
            ->willReturnMap([
                ['config', $config],
                [ClientRepositoryInterface::class, $clientRepository],
                [AccessTokenRepositoryInterface::class, $accessTokenRepository],
                [ScopeRepositoryInterface::class, $scopeRepository],
                [AuthCodeRepositoryInterface::class, $authCodeRepository],
                [RefreshTokenRepositoryInterface::class, $refreshTokenRepository],
                [MagicLinkTokenRepositoryInterface::class, $magicLinkTokenRepository],
                [UserRepositoryInterface::class, $userRepository],
                [TokenConfig::class, $tokenConfig],
            ]);
    }
}
