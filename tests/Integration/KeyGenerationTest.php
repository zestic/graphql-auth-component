<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class KeyGenerationTest extends TestCase
{
    private string $testProjectRoot;
    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Store original working directory
        $this->originalCwd = getcwd();
        
        // Create a temporary directory for testing
        $this->testProjectRoot = sys_get_temp_dir() . '/oauth-key-test-' . uniqid();
        mkdir($this->testProjectRoot, 0755, true);
        
        // Copy the key generation script to test directory
        $scriptSource = __DIR__ . '/../../bin/generate-oauth-keys.php';
        $scriptDest = $this->testProjectRoot . '/bin/generate-oauth-keys.php';
        mkdir(dirname($scriptDest), 0755, true);
        copy($scriptSource, $scriptDest);
        chmod($scriptDest, 0755);
        
        // Copy the source files needed by the script
        $this->copySourceFiles();
        
        // Change to test directory
        chdir($this->testProjectRoot);
    }

    protected function tearDown(): void
    {
        // Restore original working directory
        chdir($this->originalCwd);
        
        // Clean up test directory
        $this->removeDirectory($this->testProjectRoot);
        
        parent::tearDown();
    }

    public function testGenerateKeysCreatesAllRequiredFiles(): void
    {
        // Run the key generation script
        $output = $this->runKeyGenerationScript();
        
        // Assert script ran successfully
        $this->assertStringContainsString('✅ All keys generated successfully!', $output);
        
        // Assert JWT private key was created
        $privateKeyPath = $this->testProjectRoot . '/config/jwt/private.key';
        $this->assertFileExists($privateKeyPath);
        
        // Assert JWT public key was created
        $publicKeyPath = $this->testProjectRoot . '/config/jwt/public.key';
        $this->assertFileExists($publicKeyPath);
        
        // Assert auth config was created
        $authConfigPath = $this->testProjectRoot . '/config/autoload/auth.local.php';
        $this->assertFileExists($authConfigPath);
    }

    public function testPrivateKeyHasCorrectPermissions(): void
    {
        $this->runKeyGenerationScript();
        
        $privateKeyPath = $this->testProjectRoot . '/config/jwt/private.key';
        $permissions = fileperms($privateKeyPath) & 0777;
        
        // Assert private key has 600 permissions (owner read/write only)
        $this->assertEquals(0600, $permissions);
    }

    public function testGeneratedKeysHaveValidContent(): void
    {
        $this->runKeyGenerationScript();
        
        // Test private key content
        $privateKeyPath = $this->testProjectRoot . '/config/jwt/private.key';
        $privateKeyContent = file_get_contents($privateKeyPath);
        $this->assertStringStartsWith('-----BEGIN PRIVATE KEY-----', $privateKeyContent);
        $this->assertStringEndsWith("-----END PRIVATE KEY-----\n", $privateKeyContent);
        
        // Test public key content
        $publicKeyPath = $this->testProjectRoot . '/config/jwt/public.key';
        $publicKeyContent = file_get_contents($publicKeyPath);
        $this->assertStringStartsWith('-----BEGIN PUBLIC KEY-----', $publicKeyContent);
        $this->assertStringEndsWith("-----END PUBLIC KEY-----\n", $publicKeyContent);
        
        // Test that keys are valid OpenSSL keys
        $privateKeyResource = openssl_pkey_get_private($privateKeyContent);
        $this->assertNotFalse($privateKeyResource);

        $publicKeyResource = openssl_pkey_get_public($publicKeyContent);
        $this->assertNotFalse($publicKeyResource);
    }

    public function testAuthConfigHasCorrectStructure(): void
    {
        $this->runKeyGenerationScript();
        
        $authConfigPath = $this->testProjectRoot . '/config/autoload/auth.local.php';
        $config = include $authConfigPath;
        
        // Assert config structure
        $this->assertIsArray($config);
        $this->assertArrayHasKey('auth', $config);
        $this->assertArrayHasKey('encryptionKey', $config['auth']);
        
        // Assert encryption key is a valid base64 string
        $encryptionKey = $config['auth']['encryptionKey'];
        $this->assertIsString($encryptionKey);
        $this->assertNotEmpty($encryptionKey);
        
        // Test that it's valid base64
        $decoded = base64_decode($encryptionKey, true);
        $this->assertNotFalse($decoded);
        $this->assertEquals(32, strlen($decoded)); // Should be 32 bytes
    }

    public function testScriptFailsWhenKeysAlreadyExist(): void
    {
        // Run script first time
        $this->runKeyGenerationScript();
        
        // Run script second time - should fail
        $output = $this->runKeyGenerationScript(expectFailure: true);
        
        $this->assertStringContainsString('❌ Error: Keys already exist!', $output);
        $this->assertStringContainsString('JWT Private Key:', $output);
        $this->assertStringContainsString('JWT Public Key:', $output);
        $this->assertStringContainsString('Auth Local Config:', $output);
    }

    public function testScriptShowsConfigurableParameters(): void
    {
        $output = $this->runKeyGenerationScript();

        // Should show the OpenSSL parameters being used
        $this->assertStringContainsString('⚙️  Using: sha256, 2048 bits, RSA', $output);
    }

    public function testGeneratedKeysWorkWithAuthorizationServerFactory(): void
    {
        $this->runKeyGenerationScript();

        // Load the generated config
        $authConfigPath = $this->testProjectRoot . '/config/autoload/auth.local.php';
        $authLocalConfig = include $authConfigPath;

        // Create a mock config that includes both the generated config and the JWT paths
        $fullConfig = [
            'auth' => array_merge([
                'jwt' => [
                    'privateKeyPath' => $this->testProjectRoot . '/config/jwt/private.key',
                    'publicKeyPath'  => $this->testProjectRoot . '/config/jwt/public.key',
                    'passphrase'     => null,
                ],
            ], $authLocalConfig['auth'])
        ];

        // Test that we can create a CryptKey from the generated private key
        $privateKeyPath = $fullConfig['auth']['jwt']['privateKeyPath'];
        $passphrase = $fullConfig['auth']['jwt']['passphrase'];

        $cryptKey = new \League\OAuth2\Server\CryptKey($privateKeyPath, $passphrase);
        $this->assertInstanceOf(\League\OAuth2\Server\CryptKey::class, $cryptKey);

        // Test that the encryption key is valid
        $encryptionKey = $fullConfig['auth']['encryptionKey'];
        $this->assertIsString($encryptionKey);
        $this->assertNotEmpty($encryptionKey);

        // Verify the encryption key can be decoded
        $decoded = base64_decode($encryptionKey, true);
        $this->assertNotFalse($decoded);
        $this->assertEquals(32, strlen($decoded));
    }

    private function runKeyGenerationScript(bool $expectFailure = false): string
    {
        $command = 'php bin/generate-oauth-keys.php 2>&1';
        $output = shell_exec($command);
        
        if (!$expectFailure && $output === null) {
            $this->fail('Failed to execute key generation script');
        }
        
        return $output ?? '';
    }

    private function copySourceFiles(): void
    {
        // Copy the source directory structure needed by the script
        $sourceDir = __DIR__ . '/../../src';
        $destDir = $this->testProjectRoot . '/src';
        $this->copyDirectory($sourceDir, $destDir);
        
        // Create a minimal composer autoload file
        $autoloadContent = "<?php\n";
        $autoloadContent .= "spl_autoload_register(function (\$class) {\n";
        $autoloadContent .= "    \$prefix = 'Zestic\\\\GraphQL\\\\AuthComponent\\\\';\n";
        $autoloadContent .= "    \$base_dir = __DIR__ . '/../src/';\n";
        $autoloadContent .= "    \$len = strlen(\$prefix);\n";
        $autoloadContent .= "    if (strncmp(\$prefix, \$class, \$len) !== 0) return;\n";
        $autoloadContent .= "    \$relative_class = substr(\$class, \$len);\n";
        $autoloadContent .= "    \$file = \$base_dir . str_replace('\\\\', '/', \$relative_class) . '.php';\n";
        $autoloadContent .= "    if (file_exists(\$file)) require \$file;\n";
        $autoloadContent .= "});\n";
        
        $vendorDir = $this->testProjectRoot . '/vendor';
        mkdir($vendorDir, 0755, true);
        file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
    }

    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($source)) {
            return;
        }
        
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $item) {
            $destPath = $destination . DIRECTORY_SEPARATOR . $iterator->getSubPathName();
            
            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getRealPath(), $destPath);
            }
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        
        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        
        rmdir($directory);
    }
}
