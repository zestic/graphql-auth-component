<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Event;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;

/**
 * Event dispatched when a user has been successfully registered
 *
 * This event is fired after the user has been created and committed to the database,
 * but before any post-registration actions like sending verification emails.
 */
class UserRegisteredEvent
{
    public function __construct(
        private readonly string|int $userId,
        private readonly RegistrationContext $registrationContext,
        private readonly string $clientId,
        private readonly \DateTimeImmutable $occurredAt = new \DateTimeImmutable(),
    ) {
    }

    public function getUserId(): string|int
    {
        return $this->userId;
    }

    public function getRegistrationContext(): RegistrationContext
    {
        return $this->registrationContext;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * Get the user's email from the registration context
     */
    public function getEmail(): string
    {
        return $this->registrationContext->get('email');
    }

    /**
     * Get additional data from the registration context
     *
     * @return array<string, mixed>|null
     */
    public function getAdditionalData(): ?array
    {
        return $this->registrationContext->get('additionalData');
    }
}
