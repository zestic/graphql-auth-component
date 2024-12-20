<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

trait GenerateUniqueIdentifierTrait
{
    protected function generateUniqueIdentifier(): string
    {
        return bin2hex(random_bytes(40));
    }
}