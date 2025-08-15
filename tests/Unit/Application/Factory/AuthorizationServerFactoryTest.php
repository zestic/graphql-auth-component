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

    private string $tempKeyFile;

    // Test RSA private key for testing purposes only
    private const TEST_PRIVATE_KEY = '-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCgOYEJ63wj0VrO
D7wQhpsRr2u7pO7dPzR9s6ZQISeq4k7AnryO3SW3GbqBjJ41ODuieScY5JfyKyt5
aZmf6rdC/DYEPLtpNZi1vpMGWDTXRonY+O5h1MqmatV2jWGaEmDvPq+OvAv/satY
UWEvL8mxEtxCcZZV3ftWKtrmObQbqiBHsmlXo2K8QvAs9MGHGtAc4BVa1ImUeAbm
IKO9pfbONx6Ec3aAnLFhs2dVkG6QhcF7znU0iRDUHMok5SXnnNW7zN+kQ5prs3Px
PH0sv2JtqGIJDoR3JUik6gCkfDpftp0Tbr3Ma9jvgZ4Xhu/d+CEHLw2wm1zRDrs2
zMqtLGQXAgMBAAECggEAGWilHwaaHDACH3V9VH2UL9zKz+oa+zkfwONxd3maic3z
wTYrHtjRN1U8L3k2SahORLjDy33M3tmbvlhRxXt6boQCqO9cpRWbzw6W0EXCs3T7
PU5Ut20Aah29FlzbYoyRlL8zJgaKPJVXX90f9VS1voAIL/1FYv6d8g8/wYzbFBvJ
KPZn2A54gc5JUjM630g1Iq3yZ9pBYvTx2i4guEWqD76gJ5OHX2O5BeyRrIuEArWD
vwFAcgNhZQECnydKPXpTC1vjTuN9g40xehg/3RaRv0Lbg/ZnV83ntMH9zNT+yjUb
0sz9VEsoCfTBBgkESsElG6xS7s3EZaYnTwHKjCvZwQKBgQDb2uFMRdZVcSbWLpJv
rDuBvy6Cp3jjuGAYR/zN573oUvQPNOccoHkhGJ1lAPBhfrAr7IOMBQEkJw6ZuXv+
cS4BaacfniUt9LTF7kOGbUeWRTiKxJmqDfAFnEe78X666c+JZKF+r21DOX6r+yCi
5kSH3lbozjhw6AuFPasC+ctloQKBgQC6kPAhRSzuJttKrdk9t9GsoqLwh1lZ6gbq
hpeh82f2TrXfMxTc2IArbSHw3Rsyeua90fitpMiMeozIMpyTdHLOkJdWFls0mTdC
Nw0dJA3iVLXTZ5VourWIVO0vX0u9AsZ87/uG2/RBfdTC5YsRcBE0ACREPNelv4TM
9pFZZlH+twKBgF7fmGOqq3BZkNHSbRzFrTQzRSXakT9rnAQ+ZGiSfZAY4/r/8E+Z
LExM6/bfLdxUqD98I9QzgKeSNym9MjW9r4WqixUI0LCBLdVQGVGULNU678hqSIlq
1E4Hf6kp8G9GYGnAxDQADd15nSEoEJBbX+1l1AlInHCUogwQbZCuLMihAoGAfTAU
cb7BT2yzaYEObOOTxou7Wjr4MeVfjq+RwBJciGJ4l7TnIuoD1x/7zmwPe+gMPQNQ
IvSXvevd29haSHezMfjEE/gca0cEVWIrYop25pCBEcJH92aRuVGDdm4znDjoh51g
4jVlySxuP/lXP/Q7FvGhZEiPS6Efs4kgLyUBkDECgYEAlcbgD2cmjqLCdH0hDwTM
qDsPBRcpuxJd76/Wdgaqv9kBzGFefmAmhbVa50UCbE7Ygu4693EdoYUGNYMZAPN2
81N0MkZs6znKLnYE6Xj3Ie8GuETCSRZCAuW/w+2iH7W1zqall7w9PMDr+ZQYS77V
OxuHgKFI6RtKgUno4Qe52t8=
-----END PRIVATE KEY-----';

    protected function setUp(): void
    {
        $this->container = $this->createMock(ContainerInterface::class);
        $this->factory = new AuthorizationServerFactory();

        // Create a temporary key file for testing
        $this->tempKeyFile = tempnam(sys_get_temp_dir(), 'test_private_key');
        file_put_contents($this->tempKeyFile, self::TEST_PRIVATE_KEY);
    }

    protected function tearDown(): void
    {
        // Clean up the temporary key file
        if (file_exists($this->tempKeyFile)) {
            unlink($this->tempKeyFile);
        }
    }

    public function testSuccessfulAuthorizationServerCreation()
    {
        $config = [
            'auth' => [
                'jwt' => [
                    'privateKeyPath' => $this->tempKeyFile,
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
                    'privateKeyPath' => $this->tempKeyFile,
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
                    'privateKeyPath' => $this->tempKeyFile,
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
                    'privateKeyPath' => $this->tempKeyFile,
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
                    'privateKeyPath' => $this->tempKeyFile,
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
                    'privateKeyPath' => $this->tempKeyFile,
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
