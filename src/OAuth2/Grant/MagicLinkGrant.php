<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\OAuth2\Grant;

use AdrienGras\PKCE\PKCEUtils;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\AbstractGrant;
use League\OAuth2\Server\RequestEvent;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
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
        \DateInterval $accessTokenTTL,
    ): ResponseTypeInterface {
        $client = $this->validateClient($request);
        $scopes = $this->validateScopes($this->getRequestParameter('scope', $request, $this->defaultScope));
        $user = $this->validateUser($request, $client);

        // Validate PKCE if present
        $this->validatePkce($request);

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
        if (empty($token)) {
            throw OAuthServerException::invalidRequest('token');
        }
        $magicLinkToken = $this->magicLinkTokenRepository->findByUnexpiredToken($token);
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

    /**
     * Validate PKCE parameters if present in the magic link token
     */
    protected function validatePkce(ServerRequestInterface $request): void
    {
        $token = $this->getRequestParameter('token', $request);
        if (empty($token)) {
            throw OAuthServerException::invalidRequest('token', 'A token is required for this request');
        }

        $magicLinkToken = $this->magicLinkTokenRepository->findByUnexpiredToken($token);
        if (! $magicLinkToken) {
            throw OAuthServerException::invalidRequest('token', 'Invalid token');
        }

        $this->validatePkceChallenge($request, $magicLinkToken);
    }

    /**
     * Validate the PKCE code verifier against the stored challenge
     */
    protected function validatePkceChallenge(ServerRequestInterface $request, MagicLinkToken $magicLinkToken): void
    {
        $codeVerifier = $this->getRequestParameter('code_verifier', $request);
        if (empty($codeVerifier)) {
            throw OAuthServerException::invalidRequest('code_verifier', 'PKCE code verifier is required for this request');
        }

        // Use RFC 7636 compliant PKCE validation
        if (! PKCEUtils::validate($codeVerifier, $magicLinkToken->codeChallenge, $magicLinkToken->codeChallengeMethod)) {
            throw OAuthServerException::invalidRequest('code_verifier', 'Invalid PKCE code verifier');
        }
    }
}
