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

        // Validate PKCE if present
        $this->validatePkce($request, $client);

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
    protected function validatePkce(ServerRequestInterface $request, ClientEntityInterface $client): void
    {
        $token = $this->getRequestParameter('token', $request);
        if (is_null($token)) {
            return; // Token validation will fail elsewhere
        }

        $magicLinkToken = $this->magicLinkTokenRepository->findByUnexpiredToken($token);
        if (! $magicLinkToken || ! $magicLinkToken->getPayload()) {
            return; // No PKCE data stored, this is a regular magic link
        }

        $pkceData = json_decode($magicLinkToken->getPayload(), true);
        if (! is_array($pkceData) || ! isset($pkceData['code_challenge'])) {
            return; // No PKCE challenge stored
        }

        // PKCE validation logic:
        // 1. For public clients (mobile/SPA): PKCE is REQUIRED
        // 2. For confidential clients: PKCE is OPTIONAL but recommended
        if (! $client->isConfidential()) {
            // Public client - PKCE is mandatory
            $this->validatePkceChallenge($request, $pkceData);
        } else {
            // Confidential client - PKCE is optional but validate if present
            $codeVerifier = $this->getRequestParameter('code_verifier', $request);
            if ($codeVerifier !== null) {
                $this->validatePkceChallenge($request, $pkceData);
            }
        }
    }

    /**
     * Validate the PKCE code verifier against the stored challenge
     */
    protected function validatePkceChallenge(ServerRequestInterface $request, array $pkceData): void
    {
        $codeVerifier = $this->getRequestParameter('code_verifier', $request);
        if (is_null($codeVerifier)) {
            throw OAuthServerException::invalidRequest('code_verifier', 'PKCE code verifier is required for this request');
        }

        $storedChallenge = $pkceData['code_challenge'];
        $challengeMethod = $pkceData['code_challenge_method'] ?? 'S256';

        $computedChallenge = $this->generateCodeChallenge($codeVerifier, $challengeMethod);

        if (! hash_equals($storedChallenge, $computedChallenge)) {
            throw OAuthServerException::invalidRequest('code_verifier', 'Invalid PKCE code verifier');
        }
    }

    /**
     * Generate code challenge from verifier
     */
    protected function generateCodeChallenge(string $codeVerifier, string $method): string
    {
        switch ($method) {
            case 'S256':
                return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
            case 'plain':
                return $codeVerifier;
            default:
                throw OAuthServerException::invalidRequest('code_challenge_method', 'Unsupported challenge method');
        }
    }
}
