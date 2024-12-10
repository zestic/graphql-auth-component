<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Factory\EmailTokenFactory;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkCommunicationInterface;

class SendMagicLink
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EmailTokenFactory $emailTokenFactory,
        private SendMagicLinkCommunicationInterface $communication,
    ) {
    }

    public function send(string $email): array
    {
        try {
            if (!$user = $this->userRepository->findUserByEmail($email)) {
                return [
                    'success' => true,
                    'message' => 'Success',
                    'code' => 'MAGIC_LINK_SUCCESS',
                ];
            }

            $loginToken = $this->emailTokenFactory->createLoginToken($user->getId());
            $sent = $this->communication->send($loginToken);

            if (!$sent) {
                return [
                    'success' => false,
                    'message' => 'A system error occurred',
                    'code' => 'SYSTEM_ERROR',
                ];
            }

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
