<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class SendMagicLink
{
    public function __construct(
        private MagicLinkTokenFactory $magicLinkTokenFactory,
        private SendMagicLinkInterface $sendMagicLink,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * Send magic link with optional PKCE context
     * This is the primary method for v2.0 - supports both traditional and PKCE flows
     */
    public function send(MagicLinkContext $context): array
    {
        try {
            if (! $user = $this->userRepository->findUserByEmail($context->email)) {
                return [
                    'success' => true,
                    'message' => 'Success',
                    'code' => 'MAGIC_LINK_SUCCESS',
                ];
            }

            // Use enhanced factory method that supports both traditional and PKCE flows
            $loginToken = $this->magicLinkTokenFactory->createLoginTokenWithContext(
                (string)$user->getId(),
                $context
            );
            $this->sendMagicLink->send($loginToken);

            return [
                'success' => true,
                'message' => 'Success',
                'code' => 'MAGIC_LINK_SUCCESS',
            ];
        } catch (\Throwable $e) {
            // Log the error here if you have a logger
            return [
                'success' => false,
                'message' => 'A system error occurred',
                'code' => 'SYSTEM_ERROR',
            ];
        }
    }
}
