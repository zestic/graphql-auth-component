<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Context;

abstract class AbstractContext
{
    /** @var array<string, string> */
    public array $userAgent;

    public function __construct(
        public array $data,
    ) {
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function extractAndRemove(string $key): mixed
    {
        $value = $this->data[$key] ?? null;
        unset($this->data[$key]);

        return $value;
    }

    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }
}
