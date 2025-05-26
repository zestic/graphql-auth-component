<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Interactor;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Contract\UserCreatedHookInterface;

final class UserCreatedNullHook implements UserCreatedHookInterface
{
    public function execute(RegistrationContext $context, int|string $userId): void
    {
        return;
    }
}
