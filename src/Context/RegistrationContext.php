<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Context;

class RegistrationContext
{
    /** @var array<string, string> */
    public array $userAgent;

    public function __construct(
        public string $email,
        public array $data,
    ) {
    }

    public function get(string $key): mixed
    {
        if ($key === 'email') {
            return $this->email;
        }

        return $this->data[$key] ?? null;
    }

    public function extractAndRemove(string $key): mixed
    {
        if ($key === 'email') {
            throw new \InvalidArgumentException('Cannot remove email');
        }
        $value = $this->data[$key] ?? null;
        unset($this->data[$key]);

        return $value;
    }

    public function set(string $key, mixed $value): self
    {
        if ($key === 'email') {
            throw new \InvalidArgumentException('Cannot modify email');
        }
        $this->data[$key] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'additionalData' => $this->data,
        ];
    }
}
