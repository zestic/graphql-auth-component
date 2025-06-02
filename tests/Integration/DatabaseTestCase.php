<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Tests\Integration\DB\MigrationRunner;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

abstract class DatabaseTestCase extends TestCase
{
    protected static string $testAccessTokenId;

    protected static string $testClientId;

    protected static string $testUserId;

    public const string TEST_CLIENT_NAME = 'Test Client';
    public const string TEST_CLIENT_SECRET = 'test_secret';
    public const string TEST_EMAIL = 'test@zestic.com';
    public const string TEST_USER_DISPLAY_NAME = 'Test User';

    protected static MigrationRunner $migrationRunner;

    protected static ?PDO $pdo;

    protected static TokenConfig $tokenConfig;

    protected static string $driver;

    protected static string $schema;

    public static function setUpBeforeClass(): void
    {
        date_default_timezone_set('UTC');
        self::initializeDriver();
        self::initializePDO();
        self::initializeTokenConfig();
        self::initializeTestIds();

        // Run migrations
        self::initializeMigrations();

        // Clean up database before running test suite
        if (self::$driver === 'mysql') {
            self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            self::$pdo->exec('TRUNCATE TABLE oauth_access_tokens');
            self::$pdo->exec('TRUNCATE TABLE oauth_refresh_tokens');
            self::$pdo->exec('TRUNCATE TABLE oauth_client_scopes');
            self::$pdo->exec('TRUNCATE TABLE magic_link_tokens');
            self::$pdo->exec('TRUNCATE TABLE users');
            self::$pdo->exec('TRUNCATE TABLE oauth_scopes');
            self::$pdo->exec('TRUNCATE TABLE oauth_clients');
            self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            $schema = self::getSchema();
            self::$pdo->exec("TRUNCATE TABLE $schema.oauth_access_tokens, $schema.oauth_refresh_tokens, $schema.oauth_client_scopes, $schema.magic_link_tokens, $schema.users, $schema.oauth_scopes, $schema.oauth_clients CASCADE");
        }

        parent::setUpBeforeClass();
    }

    protected static function initializeDriver(): void
    {
        // Allow overriding the driver via environment variable
        self::$driver = $_ENV['TEST_DB_DRIVER'];
        if (! in_array(self::$driver, ['mysql', 'pgsql'])) {
            throw new \RuntimeException('Unsupported database driver: ' . self::$driver);
        }

        // Driver is now handled by repositories
    }

    protected static function initializeTestIds(): void
    {
        self::$testAccessTokenId = Uuid::uuid4()->toString();
        self::$testClientId = Uuid::uuid4()->toString();
        self::$testUserId = Uuid::uuid4()->toString();
    }

    protected static function initializePDO(): void
    {
        $host = $_ENV['TEST_DB_HOST'] ?? '127.0.0.1';
        $dbname = $_ENV['TEST_DB_NAME'] ?? 'graphql_auth_test';
        $username = $_ENV['TEST_DB_USER'] ?? 'test';
        $password = $_ENV['TEST_DB_PASS'] ?? 'password1';
        $port = $_ENV['TEST_DB_PORT'];

        if (self::$driver === 'mysql') {
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        }
        if (self::$driver === 'pgsql') {
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        }

        self::$pdo = new PDO($dsn, $username, $password);

        if (self::$driver === 'pgsql') {
            $schema = self::getSchema();
            self::$pdo->exec("SET search_path TO $schema");
        }
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    protected static function initializeTokenConfig(): void
    {
        self::$tokenConfig = new TokenConfig(
            1,
            1,
            1,
            1,
        );
    }

    protected static function initializeMigrations(): void
    {
        $config = 'phinx.' . self::$driver . '.yml';
        self::$migrationRunner = new MigrationRunner();
        self::$migrationRunner->migrate('testing', $config);
    }

    public static function tearDownAfterClass(): void
    {
        // Rollback all migrations
        self::$migrationRunner->rollback('testing', '0');

        self::$pdo = null;
    }

    protected function generateUniqueIdentifier(int $length = 40): string
    {
        if (self::$driver === 'pgsql') {
            return Uuid::uuid4()->toString();
        }

        return self::$testAccessTokenId;
    }

    protected function seedDatabase(): void
    {
        // order matters - first create dependencies, then create tokens
        $this->seedClientRepository();
        $this->seedUserRepository();
        $this->seedAccessTokenTable();
    }

    protected static function seedAccessTokenTable(): void
    {
        $accessTokenId = self::$driver === 'pgsql' ? Uuid::fromString(self::$testAccessTokenId)->toString() : self::$testAccessTokenId;
        $values = self::formatValues([
            $accessTokenId,
            self::getCurrentClientId(),
            self::$testUserId,
            self::$tokenConfig->getAccessTokenTTLDateTimeString(),
            self::$driver === 'pgsql' ? 'false' : '0',
        ]);
        self::$pdo->exec(
            "INSERT INTO " . self::getSchemaPrefix() . "oauth_access_tokens (
                id, client_id, user_id, expires_at, revoked
            ) VALUES (
                " . $values . "
            )"
        );
    }

    protected function getSeededAccessToken(): AccessTokenEntity
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setIdentifier(self::$testAccessTokenId);
        $accessToken->setClient(self::getSeededClientEntity());
        $accessToken->setUserIdentifier(self::$testUserId);
        $accessToken->setExpiryDateTime(self::$tokenConfig->getAccessTokenTTLDateTime());
        $accessToken->setRevoked(false);

        return $accessToken;
    }

    private static ?string $currentClientId = null;

    protected static function getCurrentClientId(): string
    {
        if (self::$currentClientId === null) {
            self::$currentClientId = self::$driver === 'pgsql' ? Uuid::fromString(self::$testClientId)->toString() : self::$testClientId;
        }

        return self::$currentClientId;
    }

    protected static function seedClientRepository(): ClientEntity
    {
        $clientId = self::getCurrentClientId();
        $values = self::formatValues([
            $clientId,
            self::TEST_CLIENT_NAME,
        ]);

        $table = self::getSchemaPrefix() . 'oauth_clients';
        self::$pdo->exec(
            "INSERT INTO {$table} (
                client_id, name
            ) VALUES (
                " . $values . "
            )"
        );

        return self::getSeededClientEntity();
    }

    protected static function getSeededClientEntity(): ClientEntity
    {
        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier(self::getCurrentClientId());
        $clientEntity->setName(self::TEST_CLIENT_NAME);
        $clientEntity->setRedirectUri('');
        $clientEntity->setIsConfidential(false);

        return $clientEntity;
    }

    protected static function seedUserRepository(
        string $userId = null,
        string $email = self::TEST_EMAIL,
        string $displayName = self::TEST_USER_DISPLAY_NAME,
        array $additionalData = [],
    ): void {
        $userId = $userId ?? self::$testUserId;

        $values = self::formatValues([
            $userId,
            $email,
            $displayName,
            json_encode($additionalData),
        ]);

        $table = self::getSchemaPrefix() . 'users';
        self::$pdo->exec(
            "INSERT INTO {$table} (
                id, email, display_name, additional_data
            ) VALUES (
                " . $values . "
            )"
        );
    }

    protected function setUp(): void
    {
        // Clean up test data before each test
        $this->cleanupDatabase();

        // Reset static properties
        self::$currentClientId = null;
        self::initializeTestIds();
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test
        $this->cleanupDatabase();
    }

    private function cleanupDatabase(): void
    {
        if (self::$driver === 'mysql') {
            self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            // Clean up in reverse order of dependencies
            self::$pdo->exec('TRUNCATE TABLE oauth_access_tokens');
            self::$pdo->exec('TRUNCATE TABLE oauth_refresh_tokens');
            self::$pdo->exec('TRUNCATE TABLE oauth_client_scopes');
            self::$pdo->exec('TRUNCATE TABLE magic_link_tokens');
            self::$pdo->exec('TRUNCATE TABLE users');
            self::$pdo->exec('TRUNCATE TABLE oauth_scopes');
            self::$pdo->exec('TRUNCATE TABLE oauth_clients');
            self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        } else {
            $schema = self::getSchema();
            self::$pdo->exec("TRUNCATE TABLE $schema.oauth_access_tokens, $schema.oauth_refresh_tokens, $schema.oauth_client_scopes, $schema.magic_link_tokens, $schema.users, $schema.oauth_scopes, $schema.oauth_clients CASCADE");
        }
    }

    protected static function formatValues(array $values): string
    {
        return implode(', ', array_map(function ($value) {
            return "'" . $value . "'";
        }, $values));
    }

    protected static function getSchema(): string
    {
        return $_ENV['TEST_DB_SCHEMA'] ?? '';
    }

    protected static function getSchemaPrefix(): string
    {
        $schema = self::getSchema();

        return $schema ? $schema . '.' : '';
    }
}
