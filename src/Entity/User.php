<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class User implements UserInterface
{
    public function __construct(
        public array $additionalData,
        public string $displayName,
        public string $email,
        public string|int $id,
        public ?\DateTimeInterface $verifiedAt = null,
    ) {
    }
    public function getId(): string|int
    {
        return $this->id;
    }

    public function getIdentifier(): string
    {
        return (string)$this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }
}
