<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Communication;

use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;

interface SendMagicLinkInterface
{
    public function send(MagicLinkToken $magicLinkToken): bool;
}
