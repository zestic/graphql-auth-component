<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Psr\Container\ContainerInterface;
use RuntimeException;

use function sprintf;

use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\MagicLinkGrant;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\RefreshTokenGrant;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class AuthorizationServerFactory
{
    public function __invoke(ContainerInterface $container): AuthorizationServer
    {
        $config = $container->get('config');
        $authConfig = $config['auth'] ?? throw new RuntimeException('Auth configuration not found');

        $privateKeyPath = $authConfig['jwt']['privateKeyPath'] ?? throw new RuntimeException('Private key path not configured');
        $passphrase = $authConfig['jwt']['passphrase'] ?? null;
        $encryptionKey = $authConfig['encryptionKey'] ?? throw new RuntimeException('Encryption key not configured');

        $privateKey = new \League\OAuth2\Server\CryptKey($privateKeyPath, $passphrase);

        // Get repositories from container
        $clientRepository = $container->get(ClientRepositoryInterface::class);
        $accessTokenRepository = $container->get(AccessTokenRepositoryInterface::class);
        $scopeRepository = $container->get(ScopeRepositoryInterface::class);
        $authCodeRepository = $container->get(AuthCodeRepositoryInterface::class);
        $refreshTokenRepository = $container->get(RefreshTokenRepositoryInterface::class);
        $magicLinkTokenRepository = $container->get(MagicLinkTokenRepositoryInterface::class);
        $userRepository = $container->get(UserRepositoryInterface::class);

        // Get token configuration
        $tokenConfig = $container->get(TokenConfig::class);

        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKey,
            $encryptionKey
        );

        // Configure Magic Link Grant
        $magicLinkGrant = new MagicLinkGrant(
            $magicLinkTokenRepository,
            $refreshTokenRepository,
            $userRepository,
        );
        $server->enableGrantType(
            $magicLinkGrant,
            new DateInterval(sprintf('PT%dM', $tokenConfig->getAccessTokenTTLMinutes()))
        );

        // Configure Refresh Token Grant
        $refreshTokenGrant = new RefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL(
            new DateInterval(sprintf('PT%dM', $tokenConfig->getRefreshTokenTTLMinutes()))
        );

        $server->enableGrantType(
            $refreshTokenGrant,
            new DateInterval(sprintf('PT%dM', $tokenConfig->getAccessTokenTTLMinutes()))
        );

        // Configure Authorization Code Grant with PKCE support
        $authCodeGrant = new AuthCodeGrant(
            $authCodeRepository,
            $refreshTokenRepository,
            new DateInterval('PT10M') // 10 minute auth code TTL
        );

        // PKCE is enabled by default for public clients
        $server->enableGrantType(
            $authCodeGrant,
            new DateInterval(sprintf('PT%dM', $tokenConfig->getAccessTokenTTLMinutes()))
        );

        return $server;
    }
}
