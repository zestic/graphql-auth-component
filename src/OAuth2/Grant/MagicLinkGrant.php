<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\OAuth2\Grant;

use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class MagicLinkGrant extends AbstractGrant
{
    public function __construct(
        private EmailTokenRepositoryInterface $emailTokenRepository,
        protected RefreshTokenRepositoryInterface $refreshTokenRepository,
        private UserRepositoryInterface $userRepo,
    ) {
        $this->setUserRepository($userRepo);
        $this->refreshTokenTTL = new \DateInterval('P1M');
        $this->setDefaultScope('');
    }

    public function getIdentifier(): string
    {
        return 'magic_link';
    }

    public function respondToAccessTokenRequest(
        ServerRequestInterface $request,
        ResponseTypeInterface $responseType,
        \DateInterval $accessTokenTTL
    ): ResponseTypeInterface {
        // Validate request
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));
        $user = $this->validateUser($request, $client);

        // Finalize the requested scopes
        $finalizedScopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client, $user->getIdentifier());

        // Issue and persist new access token
        $accessToken = $this->issueAccessToken($accessTokenTTL, $client, $user->getIdentifier(), $finalizedScopes);
        $this->getEmitter()->emit(new RequestEvent(RequestEvent::ACCESS_TOKEN_ISSUED, $request));
        $responseType->setAccessToken($accessToken);

        // Issue and persist new refresh token if given
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

        $emailToken = $this->emailTokenRepository->findByToken($token);
        if (!$emailToken || $emailToken->isExpired()) {
            throw OAuthServerException::invalidCredentials();
        }

        $user = $this->userRepository->findUserById(
            $this->getIdentifier(),
        );

        if (!$user) {
            $this->getEmitter()->emit(new RequestEvent(RequestEvent::USER_AUTHENTICATION_FAILED, $request));

            throw OAuthServerException::invalidCredentials();
        }

        return $user;
    }
}
