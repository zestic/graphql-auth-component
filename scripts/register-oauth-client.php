#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * OAuth2 Client Registration Script
 * 
 * This script helps you register OAuth2 clients for your application.
 * Run this script to create clients for web apps, mobile apps, etc.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;

function showUsage(): void
{
    echo "OAuth2 Client Registration Script\n";
    echo "================================\n\n";
    echo "Usage: php register-oauth-client.php [options]\n\n";
    echo "Options:\n";
    echo "  --name=<name>           Client name (required)\n";
    echo "  --type=<type>           Client type: 'public', 'confidential', or 'web-pkce' (required)\n";
    echo "  --redirect-uri=<uri>    Redirect URI (required)\n";
    echo "  --client-id=<id>        Custom client ID (optional, auto-generated if not provided)\n";
    echo "  --help                  Show this help message\n\n";
    echo "Examples:\n";
    echo "  # Register a React Native mobile app (public client with PKCE)\n";
    echo "  php register-oauth-client.php --name=\"My Mobile App\" --type=public --redirect-uri=\"myapp://auth/callback\"\n\n";
    echo "  # Register a modern web application (PKCE-enabled, no client secret)\n";
    echo "  php register-oauth-client.php --name=\"My Web App\" --type=web-pkce --redirect-uri=\"https://myapp.com/auth/callback\"\n\n";
    echo "  # Register a traditional web application (confidential client with secret)\n";
    echo "  php register-oauth-client.php --name=\"My Legacy App\" --type=confidential --redirect-uri=\"https://myapp.com/auth/callback\"\n\n";
    echo "  # Register with custom client ID\n";
    echo "  php register-oauth-client.php --name=\"My App\" --type=web-pkce --client-id=\"my-web-app\" --redirect-uri=\"https://myapp.com/callback\"\n\n";
}

function parseArguments(array $argv): array
{
    $options = [];
    
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--')) {
            if ($arg === '--help') {
                $options['help'] = true;
                continue;
            }
            
            if (str_contains($arg, '=')) {
                [$key, $value] = explode('=', substr($arg, 2), 2);
                $options[$key] = $value;
            }
        }
    }
    
    return $options;
}

function validateOptions(array $options): array
{
    $errors = [];
    
    if (!isset($options['name']) || empty($options['name'])) {
        $errors[] = "Client name is required (--name)";
    }
    
    if (!isset($options['type']) || !in_array($options['type'], ['public', 'confidential', 'web-pkce'])) {
        $errors[] = "Client type must be 'public', 'confidential', or 'web-pkce' (--type)";
    }
    
    if (!isset($options['redirect-uri']) || empty($options['redirect-uri'])) {
        $errors[] = "Redirect URI is required (--redirect-uri)";
    }
    
    return $errors;
}

function generateClientId(string $name): string
{
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $slug = trim($slug, '-');
    return $slug . '-' . bin2hex(random_bytes(4));
}

function generateClientSecret(): string
{
    return bin2hex(random_bytes(32));
}

function createPDOConnection(): PDO
{
    // You'll need to configure this for your database
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $dbname = $_ENV['DB_NAME'] ?? 'your_database';
    $username = $_ENV['DB_USER'] ?? 'your_username';
    $password = $_ENV['DB_PASS'] ?? 'your_password';
    
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    
    try {
        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        echo "Database connection failed: " . $e->getMessage() . "\n";
        echo "Please configure your database connection in this script or set environment variables:\n";
        echo "  DB_HOST, DB_NAME, DB_USER, DB_PASS\n";
        exit(1);
    }
}

function registerClient(array $options): void
{
    $pdo = createPDOConnection();
    $clientRepository = new ClientRepository($pdo);
    
    $clientId = $options['client-id'] ?? generateClientId($options['name']);
    $isConfidential = $options['type'] === 'confidential';
    $clientSecret = $isConfidential ? generateClientSecret() : null;
    $requiresPkce = in_array($options['type'], ['public', 'web-pkce']);
    
    $client = new ClientEntity();
    $client->setIdentifier($clientId);
    $client->setName($options['name']);
    $client->setRedirectUri([$options['redirect-uri']]);
    $client->setIsConfidential($isConfidential);
    
    if ($clientSecret) {
        $client->setClientSecret($clientSecret);
    }
    
    try {
        $success = $clientRepository->create($client);
        
        if ($success) {
            echo "✅ OAuth2 Client registered successfully!\n\n";
            echo "Client Details:\n";
            echo "===============\n";
            echo "Client ID:     {$clientId}\n";
            echo "Client Name:   {$options['name']}\n";
            echo "Client Type:   {$options['type']}\n";
            echo "Redirect URI:  {$options['redirect-uri']}\n";
            
            if ($clientSecret) {
                echo "Client Secret: {$clientSecret}\n";
                echo "\n⚠️  IMPORTANT: Store the client secret securely! You won't be able to retrieve it again.\n";
            } else {
                $clientTypeDesc = $options['type'] === 'web-pkce' ? 'web application with PKCE' : 'mobile app';
                echo "\n🔒 This is a public client (no secret required). Perfect for {$clientTypeDesc}!\n";
                echo "🛡️  PKCE is REQUIRED for this client type for security.\n";
            }
            
            echo "\n🔧 Integration Instructions:\n";
            echo "============================\n";
            
            if ($options['type'] === 'public') {
                echo "For React Native with PKCE:\n";
                echo "1. Install: npm install react-native-pkce-challenge\n";
                echo "2. Use this client_id in your sendMagicLink mutation: '{$clientId}'\n";
                echo "3. Set up deep linking for: '{$options['redirect-uri']}'\n";
                echo "4. Generate PKCE challenge/verifier pairs for each auth request\n";
            } elseif ($options['type'] === 'web-pkce') {
                echo "For Modern Web Application with PKCE:\n";
                echo "1. Install: npm install pkce-challenge (or similar PKCE library)\n";
                echo "2. Use this client_id in your sendMagicLink mutation: '{$clientId}'\n";
                echo "3. Generate PKCE challenge/verifier pairs in the browser\n";
                echo "4. Store code_verifier securely (sessionStorage/localStorage)\n";
                echo "5. No client secret needed - more secure for SPAs!\n";
            } else {
                echo "For Traditional Web Application:\n";
                echo "1. Use client_id: '{$clientId}'\n";
                echo "2. Use client_secret: '{$clientSecret}'\n";
                echo "3. Redirect users to: '{$options['redirect-uri']}'\n";
                echo "4. Store client_secret securely on your server\n";
                echo "5. Consider migrating to PKCE for enhanced security\n";
            }
            
        } else {
            echo "❌ Failed to register client. Please check your database connection and try again.\n";
            exit(1);
        }
        
    } catch (Exception $e) {
        echo "❌ Error registering client: " . $e->getMessage() . "\n";
        exit(1);
    }
}

// Main execution
$options = parseArguments($argv);

if (isset($options['help']) || empty($options)) {
    showUsage();
    exit(0);
}

$errors = validateOptions($options);
if (!empty($errors)) {
    echo "❌ Validation errors:\n";
    foreach ($errors as $error) {
        echo "  - {$error}\n";
    }
    echo "\nUse --help for usage information.\n";
    exit(1);
}

registerClient($options);
