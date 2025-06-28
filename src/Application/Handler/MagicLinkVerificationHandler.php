<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Handler;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkConfig;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

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
        private UserRepositoryInterface $userRepository,
        private ReissueExpiredMagicLinkToken $reissueExpiredMagicLinkToken,
        private MagicLinkConfig $config,
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

            if (! $magicLinkToken) {
                // Check if token exists but is expired
                $expiredToken = $this->magicLinkTokenRepository->findByToken($token);
                if ($expiredToken && $expiredToken->isExpired()) {
                    // Reissue the expired token
                    $reissueResult = $this->reissueExpiredMagicLinkToken->reissue($expiredToken);
                    if ($reissueResult['success']) {
                        return $this->createSuccessResponse('A new magic link has been sent to your email');
                    } else {
                        return $this->createErrorResponse($reissueResult['message']);
                    }
                }
                return $this->createErrorResponse('Invalid or expired magic link');
            }

            if ($magicLinkToken->isExpired()) {
                return $this->createErrorResponse('Invalid or expired magic link');
            }

            // Handle registration verification
            if ($magicLinkToken->tokenType === MagicLinkTokenType::REGISTRATION) {
                return $this->handleRegistrationVerification($magicLinkToken, $token);
            }

            // Handle login magic links (both PKCE and traditional)
            return $this->handleLoginMagicLink($magicLinkToken, $token);

        } catch (\Exception) {
            return $this->createErrorResponse('An error occurred while processing your request');
        }
    }

    /**
     * Handle registration verification
     */
    private function handleRegistrationVerification($magicLinkToken, string $token): ResponseInterface
    {
        $user = $this->userRepository->findUserById($magicLinkToken->userId);
        if (! $user) {
            return $this->createErrorResponse('User not found');
        }

        if ($user->getVerifiedAt() !== null) {
            // User already verified - redirect to success page or app
            return $this->redirectToAuthCallback($magicLinkToken, $token, 'User already verified');
        }

        try {
            // Verify the user
            $user->setVerifiedAt(new \DateTime());
            $this->userRepository->update($user);
            $this->magicLinkTokenRepository->delete($magicLinkToken);

            // Redirect to auth callback with success
            return $this->redirectToAuthCallback($magicLinkToken, $token, $this->config->registrationSuccessMessage);

        } catch (\Throwable) {
            return $this->createErrorResponse('A system error occurred while verifying your registration');
        }
    }

    /**
     * Handle login magic links (both PKCE and traditional)
     */
    private function handleLoginMagicLink($magicLinkToken, string $token): ResponseInterface
    {
        // Check if this is a PKCE-enabled magic link
        $pkceData = null;
        if ($magicLinkToken->getPayload()) {
            $pkceData = json_decode($magicLinkToken->getPayload(), true);
        }

        if ($pkceData && isset($pkceData['redirect_uri'])) {
            // PKCE-enabled magic link - redirect to specified app
            return $this->redirectToMobileApp($pkceData['redirect_uri'], $token, $pkceData);
        } else {
            // Regular magic link - redirect to web app magic link handler
            return $this->handleRegularMagicLink($token);
        }
    }

    /**
     * Redirect to auth callback (for both registration and login)
     */
    private function redirectToAuthCallback($magicLinkToken, string $token, string $message): ResponseInterface
    {
        // Check if this has PKCE data to determine redirect destination
        $pkceData = null;
        if ($magicLinkToken->getPayload()) {
            $pkceData = json_decode($magicLinkToken->getPayload(), true);
        }

        if ($pkceData && isset($pkceData['redirect_uri'])) {
            // PKCE flow - redirect to the specified redirect_uri
            $params = [
                'magic_link_token' => $token,
                'message' => $message,
            ];

            // Include state parameter if present for CSRF protection
            if (isset($pkceData['state'])) {
                $params['state'] = $pkceData['state'];
            }

            $finalRedirectUri = $this->config->createPkceRedirectUrl($pkceData['redirect_uri'], $params);
        } else {
            // Traditional flow - redirect to web app auth callback
            $params = [
                'magic_link_token' => $token,
                'message' => $message,
            ];
            $finalRedirectUri = $this->config->createAuthCallbackUrl($params);
        }

        $response = new \Nyholm\Psr7\Response(302);
        return $response->withHeader('Location', $finalRedirectUri);
    }

    /**
     * Create success response (for cases where we show a page instead of redirecting)
     */
    private function createSuccessResponse(string $message): ResponseInterface
    {
        $html = $this->generateSuccessPage($message);

        $response = new \Nyholm\Psr7\Response(200);
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html');
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
        // For regular magic links, redirect to the configured magic link handler
        $params = ['token' => $token];
        $redirectUri = $this->config->createMagicLinkUrl($params);

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
     * Generate a simple success page
     */
    private function generateSuccessPage(string $message): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Success</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 600px;
            margin: 100px auto;
            padding: 20px;
            text-align: center;
            background-color: #f5f5f5;
        }
        .success-container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }
        h1 {
            color: #27ae60;
            margin-bottom: 20px;
        }
        p {
            color: #666;
            line-height: 1.6;
        }
    </style>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">✅</div>
        <h1>Success!</h1>
        <p>{$message}</p>
        <p>You can now close this window.</p>
    </div>
</body>
</html>
HTML;
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
