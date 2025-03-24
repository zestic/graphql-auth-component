<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

interface TokenIdentifiersInterface
{
    public function getClientIdentifier(): string;
    public function setClientIdentifier(string $clientIdentifier): void;
    public function getUserIdentifier(): string;
    public function setUserIdentifier(string $userIdentifier): void;
}
