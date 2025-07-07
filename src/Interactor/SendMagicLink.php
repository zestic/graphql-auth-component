<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendMagicLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\MagicLinkContext;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class SendMagicLink
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private MagicLinkTokenFactory $magicLinkTokenFactory,
        private SendMagicLinkInterface $sendMagicLink,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function send(MagicLinkContext $context): array
    {
        if (!filter_var($context->get('email'), FILTER_VALIDATE_EMAIL)) {
            return [
                'success' => false,
                'message' => 'Invalid email format.',
                'code' => 'INVALID_EMAIL_FORMAT',
            ];
        }
        try {
            $client = $this->clientRepository->getClientEntity($context->get('clientId'));
            if (! $user = $this->userRepository->findUserByEmail($context->get('email'))) {
                return [
                    'success' => true,
                    'message' => 'Email not registered. Please complete registration first.',
                    'code' => 'MAGIC_LINK_REGISTRATION',
                ];
            }

            $loginToken = $this->magicLinkTokenFactory->createLoginToken(
                (string) $user->getId(),
                $client,
                $context,
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
