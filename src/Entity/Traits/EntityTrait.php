<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity\Traits;

trait EntityTrait
{
    protected ?string $identifier = null;

    /**
     * @return non-empty-string
     * @throws \RuntimeException if identifier is not set
     */
    public function getIdentifier(): string
    {
        if (empty($this->identifier)) {
            throw new \RuntimeException('Identifier not set');
        }

        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }
}
