<?php

use Symfony\Component\Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = new Dotenv();
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv->load(__DIR__ . '/../.env');
}

if (file_exists(__DIR__ . '/../.env.test')) {
    $dotenv->load(__DIR__ . '/../.env.test');
}

// Set up any global configuration or dependencies for tests
