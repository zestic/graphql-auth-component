<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthorizationServerFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\MagicLinkConfigFactory;
use Zestic\GraphQL\AuthComponent\Application\Factory\TokenConfigFactory;
use Zestic\GraphQL\AuthComponent\Application\Handler\AuthorizationRequestHandler;
use Zestic\GraphQL\AuthComponent\Application\Handler\MagicLinkVerificationHandler;
use Zestic\GraphQL\AuthComponent\Application\Handler\TokenRequestHandler;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Contract\UserCreatedHookInterface;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\AuthCodeRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\MagicLinkTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ScopeRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\UserRepository;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkConfig;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Interactor\SendMagicLink;
use Zestic\GraphQL\AuthComponent\Interactor\UserCreatedNullHook;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
            'auth' => $this->getAuthConfig(),
        ];
    }

    private function getDependencies(): array
    {
        return [
            'aliases' => [
                AccessTokenRepositoryInterface::class => AccessTokenRepository::class,
                AuthCodeRepositoryInterface::class => AuthCodeRepository::class,
                ClientRepositoryInterface::class => ClientRepository::class,
                MagicLinkTokenRepositoryInterface::class => MagicLinkTokenRepository::class,
                RefreshTokenRepositoryInterface::class => RefreshTokenRepository::class,
                ScopeRepositoryInterface::class => ScopeRepository::class,
                UserCreatedHookInterface::class => UserCreatedNullHook::class,
                UserRepositoryInterface::class => UserRepository::class,
            ],
            'factories' => [
                AuthorizationServer::class => AuthorizationServerFactory::class,
                TokenConfig::class => TokenConfigFactory::class,
                MagicLinkConfig::class => MagicLinkConfigFactory::class,
                AuthorizationRequestHandler::class => function ($container) {
                    return new AuthorizationRequestHandler(
                        $container->get(AuthorizationServer::class),
                        $container->get(UserRepositoryInterface::class),
                    );
                },
                TokenRequestHandler::class => function ($container) {
                    return new TokenRequestHandler(
                        $container->get(AuthorizationServer::class),
                    );
                },
                MagicLinkVerificationHandler::class => function ($container) {
                    return new MagicLinkVerificationHandler(
                        $container->get(MagicLinkTokenRepositoryInterface::class),
                        $container->get(UserRepositoryInterface::class),
                        $container->get(ReissueExpiredMagicLinkToken::class),
                        $container->get(MagicLinkConfig::class),
                    );
                },
                SendMagicLink::class => function ($container) {
                    return new SendMagicLink(
                        $container->get(ClientRepositoryInterface::class),
                        $container->get(MagicLinkTokenFactory::class),
                        $container->get(SendMagicLinkInterface::class),
                        $container->get(UserRepositoryInterface::class),
                    );
                },
                MagicLinkTokenFactory::class => function ($container) {
                    return new MagicLinkTokenFactory(
                        $container->get(TokenConfig::class),
                        $container->get(MagicLinkTokenRepositoryInterface::class),
                    );
                },
            ],
        ];
    }

    private function getAuthConfig(): array
    {
        return [
            'jwt' => [
                'privateKeyPath' => getcwd() . '/config/jwt/private.key',
                'publicKeyPath' => getcwd() . '/config/jwt/public.key',
                'passphrase' => null, // Set via environment variable if needed
                'keyGeneration' => [
                    'digestAlg' => 'sha256',     // Digest algorithm: sha256, sha384, sha512
                    'privateKeyBits' => 2048,        // Key size: 2048, 3072, 4096
                    'privateKeyType' => 'RSA',       // Key type: RSA, DSA, DH, EC
                ],
            ],
            'token' => [
                'accessTokenTtl' => 60, // Default 1 hour (in minutes)
                'loginTtl' => 10, // Default 10 minutes
                'refreshTokenTtl' => 10080, // Default 1 week (in minutes)
                'registrationTtl' => 1440, // Default 24 hours (in minutes)
            ],
            'magicLink' => [
                'webAppUrl' => 'https://yourapp.com', // Default web app URL
                'authCallbackPath' => '/auth/callback', // Path for auth callback
                'magicLinkPath' => '/auth/magic-link', // Path for traditional magic links
                'defaultSuccessMessage' => 'Authentication successful',
                'registrationSuccessMessage' => 'Registration verified successfully',
            ],
        ];
    }
}
