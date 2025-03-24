<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\DB;

use PDO;

class AuthPDO extends PDO
{
    public function __construct(string $dsn, string $username, string $password)
    {
        parent::__construct($dsn, $username, $password);
    }
}
