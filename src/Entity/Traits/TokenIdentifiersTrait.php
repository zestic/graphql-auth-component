<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity\Traits;

trait TokenIdentifiersTrait
{
    private string $clientIdentifier;

    private string $userIdentifier;

    public function getClientIdentifier(): string
    {
        return $this->clientIdentifier;
    }

    public function setClientIdentifier(string $clientIdentifier): void
    {
        $this->clientIdentifier = $clientIdentifier;
    }

    public function getUserIdentifier(): string
    {
        return $this->userIdentifier;
    }

    public function setUserIdentifier(string $userIdentifier): void
    {
        $this->userIdentifier = $userIdentifier;
    }
}
