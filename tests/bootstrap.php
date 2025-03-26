<?php

use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = new Dotenv();
// Load base environment file
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv->load(__DIR__ . '/../.env');
}

// Load test environment file
if (file_exists(__DIR__ . '/../.env.testing')) {
    $dotenv->load(__DIR__ . '/../.env.testing');
}

// Load database-specific environment file if TEST_DB_DRIVER is set
$dbDriver = getenv('TEST_DB_DRIVER') ?: 'mysql';
if ($dbDriver === 'pgsql' && file_exists(__DIR__ . '/../.env.testing.postgres')) {
    $dotenv->load(__DIR__ . '/../.env.testing.postgres');
}

// Set default database driver if not set
if (!getenv('TEST_DB_DRIVER')) {
    putenv('TEST_DB_DRIVER=mysql');
}

define('TESTING', true);
