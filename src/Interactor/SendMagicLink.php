<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
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

    public function send(string $email): array
    {
        try {
            if (! $user = $this->userRepository->findUserByEmail($email)) {
                return [
                    'success' => true,
                    'message' => 'Success',
                    'code' => 'MAGIC_LINK_SUCCESS',
                ];
            }

            $loginToken = $this->magicLinkTokenFactory->createLoginToken((string)$user->getId());
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
