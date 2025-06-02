<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class User implements UserInterface
{
    public function __construct(
        /** @var array<string, mixed>|null */
        public ?array $additionalData,
        public ?string $displayName,
        public string $email,
        public string|int $id,
        public ?\DateTimeInterface $verifiedAt = null,
    ) {
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    /** @return non-empty-string */
    public function getIdentifier(): string
    {
        $id = (string)$this->id;
        if ($id === '') {
            throw new \RuntimeException('User ID cannot be empty');
        }

        return $id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function isVerified(): bool
    {
        return $this->verifiedAt !== null;
    }

    public function getVerifiedAt(): ?\DateTimeInterface
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(\DateTimeInterface $verifiedAt): void
    {
        $this->verifiedAt = $verifiedAt;
    }

    public function getDisplayName(): ?string
    {
        return $this->displayName;
    }

    public function setDisplayName(?string $displayName): void
    {
        $this->displayName = $displayName;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getAdditionalData(): ?array
    {
        return $this->additionalData;
    }

    /**
     * @param array<string, mixed>|null $additionalData
     */
    public function setAdditionalData(?array $additionalData): void
    {
        $this->additionalData = $additionalData;
    }
}
