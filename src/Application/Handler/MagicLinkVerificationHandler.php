<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Handler;

use Carbon\CarbonImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkConfig;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Interactor\ReissueExpiredMagicLinkToken;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

/**
 * Handles magic link verification and redirects
 *
 * This handler validates magic link tokens and redirects users appropriately.
 * PKCE (Proof Key for Code Exchange) is required for all magic link flows.
 *
 * Flow:
 * - Validates magic link token and extracts PKCE data
 * - For registration: redirects to client app with success message
 * - For login: generates OAuth2 authorization code and redirects to client app
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

            if ($magicLinkToken->tokenType === MagicLinkTokenType::REGISTRATION) {
                return $this->handleRegistrationVerification($magicLinkToken);
            }

            return $this->handleLoginMagicLink($magicLinkToken);
        } catch (\Exception $e) {
            return $this->createErrorResponse('An error occurred while processing your request');
        }
    }

    /**
     * Handle registration verification
     */
    private function handleRegistrationVerification(MagicLinkToken $magicLinkToken): ResponseInterface
    {
        $user = $this->userRepository->findUserById($magicLinkToken->userId);
        if (! $user) {
            return $this->createErrorResponse('User not found');
        }

        if ($user->getVerifiedAt() !== null) {
            // User already verified - redirect to success page or app
            // TODO
            return $this->redirectToAuthCallback($magicLinkToken, 'User already verified', 'registration');
        }

        try {
            // Verify the user
            $user->setVerifiedAt(new CarbonImmutable());
            $this->userRepository->update($user);

            // Redirect to auth callback with success
            return $this->redirectToAuthCallback($magicLinkToken, $this->config->registrationSuccessMessage, 'registration');
        } catch (\Throwable) {
            return $this->createErrorResponse('A system error occurred while verifying your registration');
        }
    }

    private function handleLoginMagicLink(MagicLinkToken $magicLinkToken): ResponseInterface
    {
        return $this->redirectToAuthCallback($magicLinkToken, $this->config->defaultSuccessMessage, 'login');
    }

    private function redirectToAuthCallback(MagicLinkToken $magicLinkToken, string $message, string $flow): ResponseInterface
    {
        $finalRedirectUri = $this->config->createPkceRedirectUrl($magicLinkToken, $message, $flow);

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
