<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\UserEntityInterface as OAuth2UserEntityInterface;

interface UserInterface extends OAuth2UserEntityInterface
{
    public function getId(): string|int;

    public function getEmail(): string;

    public function isVerified(): bool;

    public function getVerifiedAt(): ?\DateTimeInterface;

    public function setVerifiedAt(\DateTimeInterface $verifiedAt): void;

    public function getDisplayName(): ?string;

    public function setDisplayName(?string $displayName): void;

    /**
     * @return array<string, mixed>|null
     */
    public function getAdditionalData(): ?array;

    /**
     * @param array<string, mixed>|null $additionalData
     */
    public function setAdditionalData(?array $additionalData): void;
}
