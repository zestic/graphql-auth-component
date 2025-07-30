<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Contract\UserCreatedHookInterface;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class RegisterUser
{
    public function __construct(
        private ClientRepositoryInterface $clientRepository,
        private MagicLinkTokenFactory $magicLinkTokenFactory,
        private SendVerificationLinkInterface $sendRegistrationVerification,
        private UserCreatedHookInterface $userCreatedHook,
        private UserRepositoryInterface $userRepository,
    ) {
    }

    public function register(RegistrationContext $context): array
    {
        if ($this->userRepository->emailExists($context->get('email'))) {
            return [
                'success' => false,
                'message' => 'Email already registered',
                'code' => 'EMAIL_IN_SYSTEM',
            ];
        }
        $client = $this->clientRepository->getClientEntity($context->get('clientId'));
        if (! $client) {
            return [
                'success' => false,
                'message' => 'Invalid client',
                'code' => 'INVALID_CLIENT',
            ];
        }

        try {
            $this->userRepository->beginTransaction();

            $userId = $this->userRepository->create($context);
            $this->userCreatedHook->execute($context, $userId);
            $success = $this->userRepository->commit();
        } catch (\Exception $e) {
            $this->userRepository->rollback();

            return [
                'success' => false,
                'message' => 'Registration failed due to a system error',
                'code' => 'SYSTEM_ERROR',
            ];
        }

        try {
            $token = $this->magicLinkTokenFactory->createRegistrationToken($userId, $client, $context);
            $this->sendRegistrationVerification->send($context, $token);

            return [
                'success' => true,
                'message' => 'Email registered successfully',
                'code' => 'EMAIL_REGISTERED',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Registration failed due to a failure in sending the magic link',
                'code' => 'SEND_MAGIC_LINK_FAILED',
            ];
        }
    }
}
