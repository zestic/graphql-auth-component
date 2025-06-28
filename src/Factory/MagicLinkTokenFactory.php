<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Factory;

use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

class MagicLinkTokenFactory
{
    public function __construct(
        private TokenConfig $config,
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
    ) {
    }

    public function createLoginToken(string|int $userId): MagicLinkToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getLoginTTLMinutes()} minutes");

        $token = new MagicLinkToken(
            $expiration,
            bin2hex(random_bytes(16)),
            MagicLinkTokenType::LOGIN,
            (string)$userId,
        );

        if ($this->magicLinkTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create magic link token');
    }

    /**
     * Create a login token with PKCE context
     */
    public function createLoginTokenWithContext(string|int $userId, MagicLinkContext $context): MagicLinkToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getLoginTTLMinutes()} minutes");

        // Store PKCE parameters in the payload if present
        $payload = null;
        if ($context->isPkceEnabled()) {
            $encodedPayload = json_encode($context->getPkceParameters());
            if ($encodedPayload === false) {
                throw new \Exception('Failed to encode PKCE parameters');
            }
            $payload = $encodedPayload;
        }

        $token = new MagicLinkToken(
            $expiration,
            bin2hex(random_bytes(16)),
            MagicLinkTokenType::LOGIN,
            (string)$userId,
            $payload
        );

        if ($this->magicLinkTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create magic link token');
    }

    public function createRegistrationToken(string|int $userId): MagicLinkToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getRegistrationTTLMinutes()} minutes");

        $token = new MagicLinkToken(
            $expiration,
            bin2hex(random_bytes(16)),
            MagicLinkTokenType::REGISTRATION,
            (string)$userId,
        );

        if ($this->magicLinkTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create magic link token');
    }
}
