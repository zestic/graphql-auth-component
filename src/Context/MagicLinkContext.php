<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Context;

/**
 * Context object for magic link requests with optional PKCE parameters
 */
class MagicLinkContext
{
    public function __construct(
        public readonly string $email,
        public readonly ?string $clientId = null,
        public readonly ?string $codeChallenge = null,
        public readonly ?string $codeChallengeMethod = 'S256',
        public readonly ?string $redirectUri = null,
        public readonly ?string $scope = null,
        public readonly ?string $state = null,
    ) {
    }

    /**
     * Check if this is a PKCE-enabled magic link request
     */
    public function isPkceEnabled(): bool
    {
        return $this->clientId !== null && $this->codeChallenge !== null;
    }

    /**
     * Get all PKCE parameters as an array
     */
    public function getPkceParameters(): array
    {
        if (! $this->isPkceEnabled()) {
            return [];
        }

        return [
            'client_id' => $this->clientId,
            'code_challenge' => $this->codeChallenge,
            'code_challenge_method' => $this->codeChallengeMethod,
            'redirect_uri' => $this->redirectUri,
            'scope' => $this->scope,
            'state' => $this->state,
        ];
    }

    /**
     * Create from GraphQL input array
     */
    public static function fromGraphQLInput(array $input): self
    {
        return new self(
            email: $input['email'],
            clientId: $input['clientId'] ?? null,
            codeChallenge: $input['codeChallenge'] ?? null,
            codeChallengeMethod: $input['codeChallengeMethod'] ?? 'S256',
            redirectUri: $input['redirectUri'] ?? null,
            scope: $input['scope'] ?? null,
            state: $input['state'] ?? null,
        );
    }

    /**
     * Create simple magic link context (backward compatibility)
     */
    public static function simple(string $email): self
    {
        return new self(email: $email);
    }
}
