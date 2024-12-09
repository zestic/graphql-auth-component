<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Repository;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;

interface UserRepositoryInterface
{
    public function create(RegistrationContext $context): bool;
    public function emailExists(string $email): bool;
}
