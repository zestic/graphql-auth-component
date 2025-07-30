<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ResponseInterface;
use Zestic\GraphQL\AuthComponent\OAuth2\OAuthConfig;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

class AuthenticateToken
{
    public function __construct(
        private AuthorizationServer $authorizationServer,
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
        private OAuthConfig $oauthConfig,
        private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken,
    ) {
    }

    public function authenticate(string $token, ?string $codeVerifier = null): array
    {
        $magicLinkToken = $this->magicLinkTokenRepository->findByUnexpiredToken($token);
        if (! $magicLinkToken) {
            // Check if token exists but is expired
            $expiredToken = $this->magicLinkTokenRepository->findByToken($token);
            if ($expiredToken && $expiredToken->isExpired()) {
                // Reissue the expired token
                return $this->reissueExpiredMagicLinkToken->reissue($expiredToken);
            }

            throw new \Exception('Invalid token');
        }

        if ($magicLinkToken->isExpired()) {
            // This shouldn't happen since findByUnexpiredToken filters by expiration, but just in case
            return $this->reissueExpiredMagicLinkToken->reissue($magicLinkToken);
        }

        try {
            $body = [
                'grant_type' => 'magic_link',
                'client_id' => $this->oauthConfig->getClientId(),
                'client_secret' => $this->oauthConfig->getClientSecret(),
                'token' => $token,
            ];

            // Add code_verifier for PKCE validation if provided
            if ($codeVerifier !== null) {
                $body['code_verifier'] = $codeVerifier;
            }

            $request = new ServerRequest('POST', new Uri('http://example.com/token'));
            $request = $request
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withParsedBody($body)
                ->withBody(Stream::create(http_build_query($body)));

            $response = $this->authorizationServer->respondToAccessTokenRequest($request, new Response());

            $this->magicLinkTokenRepository->delete($token);

            return $this->parseResponse($response);
        } catch (OAuthServerException $exception) {
            throw new \Exception('Authentication failed: ' . $exception->getMessage());
        }
    }

    private function parseResponse(ResponseInterface $response): array
    {
        $body = $response->getBody()->__toString();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Unable to parse response');
        }

        return [
            'accessToken' => $data['access_token'] ?? null,
            'refreshToken' => $data['refresh_token'] ?? null,
            'expiresAt' => $data['expires_at'] ?? null,
        ];
    }
}
