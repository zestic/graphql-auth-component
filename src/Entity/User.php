<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface as OAuth2UserEntityInterface;

class User implements UserInterface, OAuth2UserEntityInterface
{
    public function __construct(
        public array $additionalData,
        public string $displayName,
        public string $email,
        public string|int $id,
        public string $status,
    ) {
    }
    public function getId(): string|int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }
}
