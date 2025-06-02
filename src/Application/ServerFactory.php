<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application;

use League\OAuth2\Server\AuthorizationServer;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\MagicLinkGrant;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\RefreshTokenGrant;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ServerFactory
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private AccessTokenRepositoryInterface $accessTokenRepository,
        private ScopeRepositoryInterface $scopeRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepository,
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
        private UserRepositoryInterface $userRepository,
        private string $privateKey,
        private string $encryptionKey,
    ) {}

    public function create(): AuthorizationServer
    {
        $server = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $this->privateKey,
            $this->encryptionKey
        );

        $magicLinkGrant = new MagicLinkGrant(
            $this->magicLinkTokenRepository,
            $this->refreshTokenRepository,
            $this->userRepository,
        );
        $server->enableGrantType(
            $magicLinkGrant,
            new \DateInterval('PT1H') // 1 hour
        );

        $refreshTokenGrant = new RefreshTokenGrant($this->refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL(new \DateInterval('P1M')); // 1 month

        $server->enableGrantType(
            $refreshTokenGrant,
            new \DateInterval('PT1H') // 1 hour
        );

        return $server;
    }
}
