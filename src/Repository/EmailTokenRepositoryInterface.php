<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Repository;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;

interface EmailTokenRepositoryInterface
{
    public function create(EmailToken $emailToken): bool;
    public function delete(EmailToken|string $emailToken): bool;
    public function findByToken(string $token):?EmailToken;
}
