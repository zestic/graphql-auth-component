<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Repository;

use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;

interface MagicLinkTokenRepositoryInterface
{
    public function create(MagicLinkToken $magicLinkToken): bool;

    public function delete(MagicLinkToken|string $magicLinkToken): bool;

    public function findByUnexpiredToken(string $token): ?MagicLinkToken;

    public function findByToken(string $token, bool $checkExpiry = false): ?MagicLinkToken;
}
