<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Contract;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;

interface UserCreatedHookInterface
{
    public function execute(RegistrationContext $context, int|string $userId): void;
}
