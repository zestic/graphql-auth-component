<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Factory;

use Carbon\CarbonImmutable;
use Zestic\GraphQL\AuthComponent\Context\AbstractContext;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
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

    public function createLoginToken(string|int $userId, ClientEntity $client, MagicLinkContext $context): MagicLinkToken
    {
        return $this->createMagicLinkToken($userId, $client, $context, MagicLinkTokenType::LOGIN);
    }

    public function createRegistrationToken(string|int $userId, ClientEntity $client, RegistrationContext $context): MagicLinkToken
    {
        return $this->createMagicLinkToken($userId, $client, $context, MagicLinkTokenType::REGISTRATION);
    }

    private function createMagicLinkToken(string|int $userId, ClientEntity $client, AbstractContext $context, MagicLinkTokenType $tokenType): MagicLinkToken
    {
        $expiration = CarbonImmutable::now()->addMinutes($this->config->getLoginTTLMinutes());

        $token = new MagicLinkToken(
            $context->get('clientId'),
            $context->get('codeChallenge'),
            $context->get('codeChallengeMethod'),
            $context->get('redirectUri'),
            $context->get('state'),
            $context->get('email'),
            $expiration,
            $tokenType,
            $userId,
        );

        if ($this->magicLinkTokenRepository->create($token)) {
            return $token;
        }

        throw new \Exception('Failed to create magic link token');
    }
}
