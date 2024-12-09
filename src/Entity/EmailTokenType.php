<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

enum EmailTokenType: string
{
    case REGISTRATION = 'registration';
    case LOGIN = 'login';
}
