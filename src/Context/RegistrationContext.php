<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Context;

class RegistrationContext extends AbstractContext
{
    public function toArray(): array
    {
        return [
            'email'          => $this->data['email'],
            'additionalData' => $this->data['additionalData'],
        ];
    }
}
