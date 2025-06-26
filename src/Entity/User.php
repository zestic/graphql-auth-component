<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

class User implements UserInterface
{
    public ?string $systemId = null;

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
        if (empty($this->id)) {
            throw new \RuntimeException('ID cannot be empty');
        }

        return (string) $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getSystemId(): string|int|null 
    {
        return $this->systemId;
    }

    public function setSystemId(string|int|null $systemId): void 
    {
        $this->systemId = $systemId;
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
