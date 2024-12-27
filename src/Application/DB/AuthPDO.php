<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\DB;

use PDO;

class AuthPDO extends PDO
{
    public function __construct()
    {
        parent::__construct(
            'mysql:host=' . getenv('AUTH_DB_HOST') . ';dbname=' . getenv('AUTH_DB_NAME') . ';port=' . getenv('AUTH_DB_PORT'),
            getenv('AUTH_DB_USER'),
            getenv('AUTH_DB_PASS')
        );
    }
}
