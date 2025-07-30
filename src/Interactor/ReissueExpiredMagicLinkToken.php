<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class ReissueExpiredMagicLinkToken
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private MagicLinkTokenFactory $magicLinkTokenFactory,
        private SendMagicLinkInterface $sendMagicLink,
        private SendVerificationLinkInterface $sendVerificationLink,
        private UserRepositoryInterface $userRepository,
        private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository,
    ) {
    }

    public function reissue(MagicLinkToken $expiredToken): array
    {
        try {
            // Get the user associated with the expired token
            $user = $this->userRepository->findUserById($expiredToken->getUserId());
            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'User not found',
                    'code' => 'USER_NOT_FOUND',
                ];
            }

            $newToken = $this->createNewToken($expiredToken);
            $this->sendToken($expiredToken->tokenType, $user, $newToken);
            $this->magicLinkTokenRepository->delete($expiredToken);

            return [
                'success' => true,
                'message' => 'Token expired. A new magic link has been sent to your email.',
                'code' => 'TOKEN_EXPIRED_NEW_SENT',
            ];
        } catch (\Throwable) {
            return [
                'success' => false,
                'message' => 'A system error occurred while reissuing the token',
                'code' => 'SYSTEM_ERROR',
            ];
        }
    }

    private function createNewToken(MagicLinkToken $expiredToken): MagicLinkToken
    {
        // Get the client entity
        $client = $this->clientRepository->getClientEntity($expiredToken->clientId);
        if (! $client) {
            throw new \Exception('Invalid client for expired token');
        }

        return match ($expiredToken->tokenType) {
            MagicLinkTokenType::LOGIN => $this->createLoginToken($expiredToken, $client),
            MagicLinkTokenType::REGISTRATION => $this->createRegistrationToken($expiredToken, $client),
        };
    }

    private function createLoginToken(MagicLinkToken $expiredToken, ClientEntityInterface $client): MagicLinkToken
    {
        $context = new MagicLinkContext([
            'clientId' => $expiredToken->clientId,
            'codeChallenge' => $expiredToken->codeChallenge,
            'codeChallengeMethod' => $expiredToken->codeChallengeMethod,
            'redirectUri' => $expiredToken->redirectUri,
            'state' => $expiredToken->state,
            'email' => $expiredToken->email,
        ]);

        return $this->magicLinkTokenFactory->createLoginToken($expiredToken->getUserId(), $client, $context);
    }

    private function createRegistrationToken(MagicLinkToken $expiredToken, ClientEntityInterface $client): MagicLinkToken
    {
        $context = new RegistrationContext([
            'clientId' => $expiredToken->clientId,
            'codeChallenge' => $expiredToken->codeChallenge,
            'codeChallengeMethod' => $expiredToken->codeChallengeMethod,
            'redirectUri' => $expiredToken->redirectUri,
            'state' => $expiredToken->state,
            'email' => $expiredToken->email,
            'additionalData' => [],
        ]);

        return $this->magicLinkTokenFactory->createRegistrationToken($expiredToken->getUserId(), $client, $context);
    }

    private function sendToken(MagicLinkTokenType $tokenType, UserInterface $user, MagicLinkToken $token): void
    {
        match ($tokenType) {
            MagicLinkTokenType::LOGIN => $this->sendMagicLink->send($token),
            MagicLinkTokenType::REGISTRATION => $this->sendRegistrationVerificationLink($user, $token),
        };
    }

    private function sendRegistrationVerificationLink(UserInterface $user, MagicLinkToken $token): void
    {
        // Create a registration context from the user data
        $context = new RegistrationContext([
            'email' => $user->getEmail(),
            'additionalData' => $user->getAdditionalData() + ['displayName' => $user->getDisplayName()],
        ]);

        $this->sendVerificationLink->send($context, $token);
    }
}
