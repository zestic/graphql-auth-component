<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;

class RequestAccessToken
{
    public function __construct(
        private AuthorizationServer $authServer,
        private Psr17Factory $psr17Factory
    ) {
    }

    public function execute(string $refreshToken, string $clientId, string $clientSecret): AccessTokenEntity
    {
        $request = $this->createServerRequest($refreshToken, $clientId, $clientSecret);

        try {
            $response = $this->authServer->respondToAccessTokenRequest($request, $this->psr17Factory->createResponse());

            $result = json_decode((string) $response->getBody(), true);

            return new AccessTokenEntity(
                $result['access_token'],
                $result['refresh_token'],
                $result['expires_in']
            );
        } catch (OAuthServerException $exception) {
            // Handle the exception (e.g., log it, throw a custom exception, etc.)
            throw $exception;
        }
    }

    private function createServerRequest(string $refreshToken, string $clientId, string $clientSecret): ServerRequestInterface
    {
        $request = $this->psr17Factory->createServerRequest('POST', 'http://example.com/token');
        $request = $request->withParsedBody([
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        return $request;
    }
}
