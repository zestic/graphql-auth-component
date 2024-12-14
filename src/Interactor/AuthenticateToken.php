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
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class AuthenticateToken
{
    public function __construct(
        private AuthorizationServer $authorizationServer,
        private EmailTokenRepositoryInterface $emailTokenRepository,
        private OAuthConfig $oauthConfig,
    ) {
    }

    public function authenticate(string $token): array
    {
        $emailToken = $this->emailTokenRepository->findByToken($token);
        if (!$emailToken || $emailToken->isExpired()) {
            throw new \Exception('Invalid or expired token');
        }

        try {
            $request = new ServerRequest('POST', new Uri('http://example.com/token'));
            $request = $request
                ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->withBody(Stream::create(http_build_query([
                    'grant_type' => 'magic_link',
                    'client_id' => $this->oauthConfig->getClientId(),
                    'client_secret' => $this->oauthConfig->getClientSecret(),
                    'token' => $token,
                ])));

            $response = $this->authorizationServer->respondToAccessTokenRequest($request, new Response());

            $this->emailTokenRepository->delete($token);

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
            'expiresIn' => $data['expires_in'] ?? null,
        ];
    }
}
