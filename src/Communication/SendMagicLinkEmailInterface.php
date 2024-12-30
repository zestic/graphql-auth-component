<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Communication;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;

interface SendMagicLinkEmailInterface
{
    public function send(EmailToken $emailToken): bool;
}
