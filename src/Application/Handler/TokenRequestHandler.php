<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Handler;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TokenRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private AuthorizationServer $authorizationServer,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Ensure the request method is POST
            if ($request->getMethod() !== 'POST') {
                throw OAuthServerException::invalidRequest('grant_type', 'Token requests must use POST method');
            }
            
            // Ensure the content type is correct
            $contentType = $request->getHeaderLine('Content-Type');
            if (!str_contains($contentType, 'application/x-www-form-urlencoded')) {
                throw OAuthServerException::invalidRequest(
                    'content_type', 
                    'Token requests must use application/x-www-form-urlencoded content type'
                );
            }
            
            // Validate and respond to the access token request
            $response = new \Nyholm\Psr7\Response();
            return $this->authorizationServer->respondToAccessTokenRequest($request, $response);
            
        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new \Nyholm\Psr7\Response());
        } catch (\Exception $exception) {
            $oauthException = OAuthServerException::serverError($exception->getMessage());
            return $oauthException->generateHttpResponse(new \Nyholm\Psr7\Response());
        }
    }


}
