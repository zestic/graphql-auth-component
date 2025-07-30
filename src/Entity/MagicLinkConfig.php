<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class MagicLinkConfig
{
    public function __construct(
        public readonly string $webAppUrl,
        public readonly string $authCallbackPath,
        public readonly string $magicLinkPath,
        public readonly string $defaultSuccessMessage,
        public readonly string $registrationSuccessMessage,
    ) {
    }

    /**
     * Get the full URL for auth callback
     */
    public function getAuthCallbackUrl(): string
    {
        return rtrim($this->webAppUrl, '/') . $this->authCallbackPath;
    }

    /**
     * Get the full URL for magic link handling
     */
    public function getMagicLinkUrl(): string
    {
        return rtrim($this->webAppUrl, '/') . $this->magicLinkPath;
    }

    /**
     * Build a redirect URL with query parameters
     */
    public function buildRedirectUrl(string $baseUrl, array $params): string
    {
        if (empty($params)) {
            return $baseUrl;
        }
        $separator = str_contains($baseUrl, '?') ? '&' : '?';

        return $baseUrl . $separator . http_build_query($params);
    }

    /**
     * Create auth callback URL with parameters
     */
    public function createAuthCallbackUrl(array $params = []): string
    {
        return $this->buildRedirectUrl($this->getAuthCallbackUrl(), $params);
    }

    /**
     * Create magic link URL with parameters
     */
    public function createMagicLinkUrl(array $params = []): string
    {
        return $this->buildRedirectUrl($this->getMagicLinkUrl(), $params);
    }

    /**
     * Create redirect URL for PKCE flows
     */
    public function createPkceRedirectUrl(MagicLinkToken $magicLinkToken, string $message, string $flow = 'login'): string
    {
        $params = [
            'flow' => $flow,
            'token' => $magicLinkToken->token,
            'state' => $magicLinkToken->state,
        ];

        return $this->buildRedirectUrl($magicLinkToken->redirectUri, $params);
    }
}
