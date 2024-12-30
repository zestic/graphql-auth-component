<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Communication;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;

interface SendVerificationEmailInterface
{
    public function send(RegistrationContext $context, EmailToken $token): void;
}
