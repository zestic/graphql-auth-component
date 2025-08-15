<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Event\Handler;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Zestic\GraphQL\AuthComponent\Communication\SendVerificationLinkInterface;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;
use Zestic\GraphQL\AuthComponent\Factory\MagicLinkTokenFactory;

/**
 * Event handler that sends verification emails when users are registered
 *
 * This handler listens for UserRegisteredEvent and sends a verification email
 * with a magic link token to the newly registered user.
 */
class SendVerificationEmailHandler
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly MagicLinkTokenFactory $magicLinkTokenFactory,
        private readonly SendVerificationLinkInterface $sendVerificationLink,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Handle the UserRegisteredEvent by sending a verification email
     */
    public function __invoke(UserRegisteredEvent $event): void
    {
        try {
            $client = $this->clientRepository->getClientEntity($event->getClientId());
            if (! $client) {
                $this->logger->error('Failed to send verification email: Invalid client', [
                    'clientId' => $event->getClientId(),
                    'userId' => $event->getUserId(),
                    'email' => $event->getEmail(),
                ]);

                return;
            }

            // Create the magic link token for registration verification
            $token = $this->magicLinkTokenFactory->createRegistrationToken(
                $event->getUserId(),
                $client,
                $event->getRegistrationContext()
            );

            // Send the verification email
            $this->sendVerificationLink->send($event->getRegistrationContext(), $token);

            $this->logger->info('Verification email sent successfully', [
                'userId' => $event->getUserId(),
                'email' => $event->getEmail(),
                'clientId' => $event->getClientId(),
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to send verification email', [
                'userId' => $event->getUserId(),
                'email' => $event->getEmail(),
                'clientId' => $event->getClientId(),
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            // Don't rethrow - we don't want to break the registration process
            // if email sending fails. The user is already registered successfully.
        }
    }
}
