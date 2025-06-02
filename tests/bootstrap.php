<?php

use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = new Dotenv();

// Load test environment file
if (file_exists(__DIR__ . '/../.env.testing')) {
    $dotenv->load(__DIR__ . '/../.env.testing');
}

// Find the --testsuite argument
$dbDriver = null;
for ($i = 1; $i < count($_SERVER['argv']); $i++) {
    if ($_SERVER['argv'][$i] === '--testsuite' && isset($_SERVER['argv'][$i + 1])) {
        $suite = strtolower($_SERVER['argv'][$i + 1]);
        if (str_contains($suite, 'postgres')) {
            $dbDriver = 'pgsql';
        } elseif (str_contains($suite, 'mysql')) {
            $dbDriver = 'mysql';
        }

        break;
    }
}

// Load database-specific environment file
if ($dbDriver) {
    $envFile = "/../.env.testing.{$dbDriver}";
    if (file_exists(__DIR__ . $envFile)) {
        $dotenv->load(__DIR__ . $envFile);
    }
}

// Set timezone for consistent testing
date_default_timezone_set('America/Chicago');

define('TESTING', true);
