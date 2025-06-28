<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

/**
 * Handles magic link verification and redirects
 *
 * This handler validates magic link tokens and redirects users appropriately:
 * - For PKCE-enabled requests: redirects to mobile app with verified token
 * - For regular requests: redirects to web application or shows success page
 */
class MagicLinkVerificationHandler implements RequestHandlerInterface
{
    public function __construct(
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $token = $queryParams['token'] ?? null;

        if (! $token) {
            return $this->createErrorResponse('Missing token parameter');
        }

        try {
            $magicLinkToken = $this->magicLinkTokenRepository->findByUnexpiredToken($token);

            if (! $magicLinkToken || $magicLinkToken->isExpired()) {
                return $this->createErrorResponse('Invalid or expired magic link');
            }

            // Check if this is a PKCE-enabled magic link
            $pkceData = null;
            if ($magicLinkToken->getPayload()) {
                $pkceData = json_decode($magicLinkToken->getPayload(), true);
            }

            if ($pkceData && isset($pkceData['redirect_uri'])) {
                // PKCE-enabled magic link - redirect to mobile app
                return $this->redirectToMobileApp($pkceData['redirect_uri'], $token, $pkceData);
            } else {
                // Regular magic link - redirect to web app or show success
                return $this->handleRegularMagicLink($token);
            }

        } catch (\Exception $e) {
            return $this->createErrorResponse('An error occurred while processing your request');
        }
    }

    /**
     * Redirect to mobile app with verified magic link token
     */
    private function redirectToMobileApp(string $redirectUri, string $token, array $pkceData): ResponseInterface
    {
        $params = [
            'magic_link_token' => $token,
        ];

        // Include state parameter if present for CSRF protection
        if (isset($pkceData['state'])) {
            $params['state'] = $pkceData['state'];
        }

        $finalRedirectUri = $redirectUri . '?' . http_build_query($params);

        $response = new \Nyholm\Psr7\Response(302);

        return $response->withHeader('Location', $finalRedirectUri);
    }

    /**
     * Handle regular magic link (non-PKCE)
     */
    private function handleRegularMagicLink(string $token): ResponseInterface
    {
        // For regular magic links, you might want to:
        // 1. Redirect to a web application with the token
        // 2. Show a success page
        // 3. Auto-login the user

        // Example: redirect to web app
        $webAppUrl = $_ENV['WEB_APP_URL'] ?? 'https://yourapp.com';
        $redirectUri = $webAppUrl . '/auth/magic-link?token=' . urlencode($token);

        $response = new \Nyholm\Psr7\Response(302);

        return $response->withHeader('Location', $redirectUri);
    }

    /**
     * Create an error response
     */
    private function createErrorResponse(string $message): ResponseInterface
    {
        $html = $this->generateErrorPage($message);

        $response = new \Nyholm\Psr7\Response(400);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Generate a simple error page
     */
    private function generateErrorPage(string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Magic Link Error</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px;
            margin: 100px auto;
            padding: 20px;
            text-align: center;
            background-color: #f5f5f5;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .error-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        h1 {
            color: #e74c3c;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">⚠️</div>
        <h1>Magic Link Error</h1>
        <p>{$message}</p>
        <p>Please request a new magic link or contact support if the problem persists.</p>
    </div>
</body>
</html>
HTML;
    }
}
