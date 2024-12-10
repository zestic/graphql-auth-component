<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Repository;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;

interface UserRepositoryInterface
{
    public function create(RegistrationContext $context): string|int;
    public function emailExists(string $email): bool;
    public function findUserByEmail(string $email): ?UserInterface;
}
