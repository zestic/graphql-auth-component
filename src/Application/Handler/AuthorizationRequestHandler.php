<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Handler;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class AuthorizationRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private AuthorizationServer $authorizationServer,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Validate the authorization request
            $authRequest = $this->authorizationServer->validateAuthorizationRequest($request);

            // Get user from request (assuming user is already authenticated)
            $userId = $this->getUserIdFromRequest($request);
            if (! $userId) {
                throw OAuthServerException::accessDenied('User not authenticated');
            }

            $user = $this->userRepository->findUserById($userId);
            if (! $user) {
                throw OAuthServerException::accessDenied('User not found');
            }

            // Set the user on the authorization request
            $authRequest->setUser($user);

            // Check if user has approved this client before or auto-approve
            $isApproved = $this->isClientApproved($request, $authRequest->getClient()->getIdentifier(), $userId);
            $authRequest->setAuthorizationApproved($isApproved);

            // Complete the authorization request
            $response = new \Nyholm\Psr7\Response();

            return $this->authorizationServer->completeAuthorizationRequest($authRequest, $response);

        } catch (OAuthServerException $exception) {
            return $exception->generateHttpResponse(new \Nyholm\Psr7\Response());
        } catch (\Exception $exception) {
            $oauthException = OAuthServerException::serverError($exception->getMessage());

            return $oauthException->generateHttpResponse(new \Nyholm\Psr7\Response());
        }
    }

    /**
     * Extract user ID from the request
     * This could come from session, JWT token, or other authentication mechanism
     */
    private function getUserIdFromRequest(ServerRequestInterface $request): ?string
    {
        // Check for user ID in various places

        // 1. From parsed body (form submission)
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody['user_id'])) {
            return (string) $parsedBody['user_id'];
        }

        // 2. From query parameters
        $queryParams = $request->getQueryParams();
        if (isset($queryParams['user_id'])) {
            return (string) $queryParams['user_id'];
        }

        // 3. From request attributes (set by middleware)
        $userId = $request->getAttribute('user_id');
        if ($userId) {
            return (string) $userId;
        }

        // 4. From Authorization header (Bearer token)
        $authHeader = $request->getHeaderLine('Authorization');
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            // This would need to be decoded/validated in a real implementation
            // For now, we'll assume it contains the user ID
            return $this->extractUserIdFromToken($matches[1]);
        }

        return null;
    }

    /**
     * Extract user ID from a token
     * In a real implementation, this would validate and decode a JWT or session token
     */
    private function extractUserIdFromToken(string $token): ?string
    {
        // Placeholder implementation
        // In reality, you'd validate the token and extract the user ID
        if (strlen($token) > 0) {
            return $token; // Simplified for testing
        }

        return null;
    }

    /**
     * Check if the user has previously approved this client
     * For now, we'll auto-approve all requests, but this could be enhanced
     * to check a user_client_approvals table or similar
     */
    private function isClientApproved(ServerRequestInterface $request, string $clientId, string $userId): bool
    {
        // Check for explicit approval in request
        $parsedBody = $request->getParsedBody();
        if (is_array($parsedBody) && isset($parsedBody['approve'])) {
            return (bool) $parsedBody['approve'];
        }

        $queryParams = $request->getQueryParams();
        if (isset($queryParams['approve'])) {
            return (bool) $queryParams['approve'];
        }

        // Auto-approve for now (in production, you might want to show a consent screen)
        return true;
    }
}
