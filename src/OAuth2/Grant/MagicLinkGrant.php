<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\OAuth2\Grant;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class MagicLinkGrant extends AbstractGrant
{
    public function __construct(
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
        private RefreshTokenRepositoryInterface $refreshTokenRepo,
        private UserRepositoryInterface $userRepo,
    ) {
        $this->setUserRepository($userRepo);
        $this->setRefreshTokenRepository($refreshTokenRepo);
        $this->refreshTokenTTL = new \DateInterval('P1M');
        $this->setDefaultScope('');
    }

    public function getIdentifier(): string
    {
        return 'magic_link';
    }

    /**
     * @return non-empty-string
     */
    protected function generateUniqueIdentifier(int $length = 40): string
    {
        /** @var non-empty-string */
        return $this->refreshTokenRepo->generateUniqueIdentifier($length);
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));
        $user = $this->validateUser($request, $client);

        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $user->getIdentifier());

        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $user->getIdentifier(), $finalizedScopes);
        $this->getEmitter()->emit(new RequestEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request));
        $responseType->setAccessToken($accessToken);

        $refreshToken = $this->issueRefreshToken($accessToken);

        if ($refreshToken !== null) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::REFRESH_TOKEN_ISSUED, $request));
            $responseType->setRefreshToken($refreshToken);
        }

        return $responseType;
    }

    protected function validateUser(ServerRequestInterface $request, ClientEntityInterface $client): UserEntityInterface
    {
        $token = $this->getRequestParameter('token', $request);
        if (is_null($token)) {
            throw OAuthServerException::invalidRequest('token');
        }
        $magicLinkToken = $this->magicLinkTokenRepository->findByToken($token);
        if (! $magicLinkToken || $magicLinkToken->isExpired()) {
            throw OAuthServerException::invalidRequest('token', 'Invalid or expired token');
        }

        $user = $this->userRepo->findUserById($magicLinkToken->getUserId());

        if (! $user) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::invalidCredentials();
        }

        return $user;
    }
}
