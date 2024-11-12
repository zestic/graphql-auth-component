<?php

declare(strict_types=1);

namespace Zestic\GraphQLAuthComponent;

use Zestic\GraphQLAuthComponent\Context\RegistrationContext;

interface VerifyRegistrationCommunicationInterface
{
    public function send(RegistrationContext $context): void;
}
