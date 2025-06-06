<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Communication;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;

interface SendVerificationLinkInterface
{
    public function send(RegistrationContext $context, MagicLinkToken $token): void;
}
