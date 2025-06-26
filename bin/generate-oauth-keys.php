#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * OAuth2 Key Generation Script
 * 
 * This script generates the necessary keys for OAuth2 authentication:
 * - JWT private/public key pair for token signing
 * - Encryption key for authorization/refresh codes
 * 
 * Usage: php bin/generate-oauth-keys.php
 * Composer: composer run generate-keys
 */

// Ensure we're running from the project root
$projectRoot = dirname(__DIR__);
chdir($projectRoot);

// Load autoloader if available (for Defuse\Crypto\Key)
if (file_exists($projectRoot . '/vendor/autoload.php')) {
    require_once $projectRoot . '/vendor/autoload.php';
}

class OAuthKeyGenerator
{
    private string $projectRoot;
    private array $config;

    public function __construct(string $projectRoot)
    {
        $this->projectRoot = $projectRoot;
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        // Load default configuration
        $configProvider = new \Zestic\GraphQL\AuthComponent\Application\ConfigProvider();
        $config = $configProvider();
        $this->config = $config['auth'] ?? [];

        // Load additional configuration from autoload directory (if exists)
        $autoloadDir = $this->projectRoot . '/config/autoload';
        if (is_dir($autoloadDir)) {
            foreach (glob($autoloadDir . '/*.php') as $configFile) {
                if (basename($configFile) !== 'auth.local.php') { // Skip the generated file
                    $additionalConfig = include $configFile;
                    if (isset($additionalConfig['auth'])) {
                        $this->config = array_merge_recursive($this->config, $additionalConfig['auth']);
                    }
                }
            }
        }
    }

    public function generate(): void
    {
        echo "🔐 OAuth2 Key Generation Script\n";
        echo "================================\n\n";

        // Check if keys already exist
        if ($this->keysExist()) {
            echo "❌ Error: Keys already exist!\n";
            echo "   Remove existing keys first if you want to regenerate them.\n";
            echo "   Existing keys found:\n";
            $this->listExistingKeys();
            exit(1);
        }

        try {
            // Generate JWT keys
            $this->generateJwtKeys();
            
            // Generate encryption key
            $this->generateEncryptionKey();
            
            echo "\n✅ All keys generated successfully!\n";
            echo "\n📋 Next steps:\n";
            echo "   1. Update your application configuration to use these keys\n";
            echo "   2. Ensure the private key is kept secure and out of web root\n";
            echo "   3. Distribute the public key to any services that validate tokens\n";
            echo "   4. Add the encryption key to your environment configuration\n\n";
            
        } catch (Exception $e) {
            echo "❌ Error generating keys: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    private function keysExist(): bool
    {
        $jwtPrivateKeyPath = $this->getJwtPrivateKeyPath();
        $jwtPublicKeyPath = $this->getJwtPublicKeyPath();
        $authLocalConfigPath = $this->getAuthLocalConfigPath();

        return file_exists($jwtPrivateKeyPath) || 
               file_exists($jwtPublicKeyPath) || 
               file_exists($authLocalConfigPath);
    }

    private function listExistingKeys(): void
    {
        $paths = [
            'JWT Private Key' => $this->getJwtPrivateKeyPath(),
            'JWT Public Key' => $this->getJwtPublicKeyPath(),
            'Auth Local Config' => $this->getAuthLocalConfigPath(),
        ];

        foreach ($paths as $name => $path) {
            if (file_exists($path)) {
                echo "   - $name: $path\n";
            }
        }
    }

    private function generateJwtKeys(): void
    {
        echo "🔑 Generating JWT key pair...\n";

        $privateKeyPath = $this->getJwtPrivateKeyPath();
        $publicKeyPath = $this->getJwtPublicKeyPath();

        // Create directory if it doesn't exist
        $jwtDir = dirname($privateKeyPath);
        if (!is_dir($jwtDir)) {
            if (!mkdir($jwtDir, 0755, true)) {
                throw new Exception("Failed to create JWT directory: $jwtDir");
            }
            echo "   📁 Created directory: $jwtDir\n";
        }

        // Generate private key with configurable parameters
        $keyGenConfig = $this->getKeyGenerationConfig();
        $keyTypeNames = [
            OPENSSL_KEYTYPE_RSA => 'RSA',
            OPENSSL_KEYTYPE_DSA => 'DSA',
            OPENSSL_KEYTYPE_DH => 'DH',
            OPENSSL_KEYTYPE_EC => 'EC',
        ];
        $keyTypeName = $keyTypeNames[$keyGenConfig['private_key_type']] ?? 'Unknown';
        echo "   ⚙️  Using: {$keyGenConfig['digest_alg']}, {$keyGenConfig['private_key_bits']} bits, $keyTypeName\n";

        $privateKeyResource = openssl_pkey_new($keyGenConfig);

        if (!$privateKeyResource) {
            throw new Exception('Failed to generate private key: ' . openssl_error_string());
        }

        // Export private key
        if (!openssl_pkey_export($privateKeyResource, $privateKeyPem)) {
            throw new Exception('Failed to export private key: ' . openssl_error_string());
        }

        // Save private key
        if (file_put_contents($privateKeyPath, $privateKeyPem) === false) {
            throw new Exception("Failed to save private key to: $privateKeyPath");
        }

        // Set secure permissions on private key
        chmod($privateKeyPath, 0600);
        echo "   🔐 Private key saved: $privateKeyPath\n";

        // Extract and save public key
        $publicKeyDetails = openssl_pkey_get_details($privateKeyResource);
        if (!$publicKeyDetails || !isset($publicKeyDetails['key'])) {
            throw new Exception('Failed to extract public key');
        }

        if (file_put_contents($publicKeyPath, $publicKeyDetails['key']) === false) {
            throw new Exception("Failed to save public key to: $publicKeyPath");
        }

        echo "   🔓 Public key saved: $publicKeyPath\n";
    }

    private function generateEncryptionKey(): void
    {
        echo "\n🔒 Generating encryption key...\n";

        // Generate a strong encryption key using the same method as OAuth2 docs
        $encryptionKey = base64_encode(random_bytes(32));
        
        echo "   🎲 Generated encryption key\n";

        // Save to auth.local.php
        $authLocalConfigPath = $this->getAuthLocalConfigPath();
        $configDir = dirname($authLocalConfigPath);
        
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0755, true)) {
                throw new Exception("Failed to create config directory: $configDir");
            }
            echo "   📁 Created directory: $configDir\n";
        }

        $configContent = "<?php\n\n";
        $configContent .= "declare(strict_types=1);\n\n";
        $configContent .= "/**\n";
        $configContent .= " * Local authentication configuration\n";
        $configContent .= " * This file contains sensitive keys and should not be committed to version control\n";
        $configContent .= " */\n\n";
        $configContent .= "return [\n";
        $configContent .= "    'auth' => [\n";
        $configContent .= "        'encryptionKey' => '$encryptionKey',\n";
        $configContent .= "    ],\n";
        $configContent .= "];\n";

        if (file_put_contents($authLocalConfigPath, $configContent) === false) {
            throw new Exception("Failed to save encryption key to: $authLocalConfigPath");
        }

        echo "   💾 Encryption key saved: $authLocalConfigPath\n";
        echo "   ⚠️  Remember to add this file to your .gitignore!\n";
    }

    private function getJwtPrivateKeyPath(): string
    {
        // Allow override via environment variable for Docker
        if ($envPath = getenv('JWT_PRIVATE_KEY_PATH')) {
            return $envPath;
        }

        $path = $this->config['jwt']['privateKeyPath'] ?? 'config/jwt/private.key';
        return $this->isAbsolutePath($path) ? $path : $this->projectRoot . '/' . $path;
    }

    private function getJwtPublicKeyPath(): string
    {
        // Allow override via environment variable for Docker
        if ($envPath = getenv('JWT_PUBLIC_KEY_PATH')) {
            return $envPath;
        }

        $path = $this->config['jwt']['publicKeyPath'] ?? 'config/jwt/public.key';
        return $this->isAbsolutePath($path) ? $path : $this->projectRoot . '/' . $path;
    }

    private function getAuthLocalConfigPath(): string
    {
        // Allow override via environment variable for Docker
        if ($envPath = getenv('AUTH_LOCAL_CONFIG_PATH')) {
            return $envPath;
        }

        return $this->projectRoot . '/config/autoload/auth.local.php';
    }

    private function getKeyGenerationConfig(): array
    {
        $keyGenConfig = $this->config['jwt']['keyGeneration'] ?? [];

        // Convert camelCase config to OpenSSL parameter names
        $opensslConfig = [
            'digest_alg' => $keyGenConfig['digestAlg'] ?? 'sha256',
            'private_key_bits' => $keyGenConfig['privateKeyBits'] ?? 2048,
        ];

        // Handle key type conversion
        $keyType = $keyGenConfig['privateKeyType'] ?? 'RSA';
        $opensslConfig['private_key_type'] = match (strtoupper($keyType)) {
            'RSA' => OPENSSL_KEYTYPE_RSA,
            'DSA' => OPENSSL_KEYTYPE_DSA,
            'DH' => OPENSSL_KEYTYPE_DH,
            'EC' => OPENSSL_KEYTYPE_EC,
            default => OPENSSL_KEYTYPE_RSA,
        };

        return $opensslConfig;
    }

    private function isAbsolutePath(string $path): bool
    {
        return $path[0] === '/' || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Za-z]:/', $path));
    }
}

// Run the generator
try {
    $generator = new OAuthKeyGenerator($projectRoot);
    $generator->generate();
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
