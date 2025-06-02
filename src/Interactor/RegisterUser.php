<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Contract\UserCreatedHookInterface;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class RegisterUser
{
    public function __construct(
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

        try {
            $this->userRepository->beginTransaction();

            $userId = $this->userRepository->create($context);
            $this->userCreatedHook->execute($context, $userId);
            $token = $this->magicLinkTokenFactory->createRegistrationToken($userId);
            $this->sendRegistrationVerification->send($context, $token);

            $this->userRepository->commit();

            return [
                'success' => true,
                'message' => 'Email registered successfully',
                'code' => 'EMAIL_REGISTERED',
            ];
        } catch (\Exception $e) {
            $this->userRepository->rollback();

            return [
                'success' => false,
                'message' => 'Registration failed due to a system error',
                'code' => 'SYSTEM_ERROR',
            ];
        }
    }
}
