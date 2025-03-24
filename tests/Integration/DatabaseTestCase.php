<?php

namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Tests\Integration\DB\MigrationRunner;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

abstract class DatabaseTestCase extends TestCase
{
    const string TEST_ACCESS_TOKEN_ID = 'test_access_token';
    const string TEST_CLIENT_ID = 'test_client';
    const string TEST_CLIENT_NAME = 'Test Client';
    const string TEST_CLIENT_SECRET = 'test_secret';
    const string TEST_EMAIL = 'test@zestic.com';
    const string TEST_USER_DISPLAY_NAME = 'Test User';
    const string TEST_USER_ID = 'test_user';

    protected static MigrationRunner $migrationRunner;
    protected static ?PDO $pdo;
    protected static TokenConfig $tokenConfig;
    protected static string $driver = 'mysql'; // Default to MySQL

    public static function setUpBeforeClass(): void
    {
        self::initializeDriver();
        self::initializePDO();
        self::initializeTokenConfig();
        self::initializeMigrations();

        parent::setUpBeforeClass();
    }

    protected static function initializeDriver(): void
    {
        // Allow overriding the driver via environment variable
        self::$driver = $_ENV['TEST_DB_DRIVER'] ?? 'mysql';
        if (!in_array(self::$driver, ['mysql', 'pgsql'])) {
            throw new \RuntimeException('Unsupported database driver: ' . self::$driver);
        }
    }

    protected static function initializePDO(): void
    {
        if (self::$driver === 'mysql') {
            $host = $_ENV['TEST_DB_HOST'];
            $dbname = $_ENV['TEST_DB_NAME'];
            $username = $_ENV['TEST_DB_USER'];
            $password = $_ENV['TEST_DB_PASS'];
            $port = $_ENV['TEST_DB_PORT'];
            $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        } else {
            $host = $_ENV['TEST_PG_HOST'];
            $dbname = $_ENV['TEST_PG_DB_NAME'];
            $username = $_ENV['TEST_PG_USER'];
            $password = $_ENV['TEST_PG_PASS'];
            $port = $_ENV['TEST_PG_PORT'];
            $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
        }

        self::$pdo = new PDO($dsn, $username, $password);
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
        $config = self::$driver === 'mysql' ? 'phinx.mysql.yml' : 'phinx.postgres.yml';
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
        return bin2hex(random_bytes($length));
    }

    protected function seedDatabase(): void
    {
        // order matters
        $this->seedClientRepository();
        $this->seedUserRepository();
        $this->seedAccessTokenTable();
    }

    protected static function seedAccessTokenTable(): void
    {
        $values = self::formatValues([
            self::TEST_ACCESS_TOKEN_ID,
            self::TEST_CLIENT_ID,
            self::TEST_USER_ID,
            self::$tokenConfig->getAccessTokenTTLDateTimeString(),
        ]);
        self::$pdo->exec(
            "INSERT INTO oauth_access_tokens (
                id, client_id, user_id, expires_at
            ) VALUES (
                " . $values . "
            )"
        );
    }

    protected function getSeededAccessToken(): AccessTokenEntity
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setIdentifier(self::TEST_ACCESS_TOKEN_ID);
        $accessToken->setClient(self::getSeededClientEntity());
        $accessToken->setUserIdentifier(self::TEST_USER_ID);
        $accessToken->setExpiryDateTime(self::$tokenConfig->getAccessTokenTTLDateTime());

        return $accessToken;
    }

    protected static function seedClientRepository(): ClientEntity
    {
        $values = self::formatValues([
            self::TEST_CLIENT_ID,
            self::TEST_CLIENT_NAME,
        ]);

        self::$pdo->exec(
            "INSERT INTO oauth_clients (
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
        $clientEntity->setIdentifier(self::TEST_CLIENT_ID);
        $clientEntity->setName(self::TEST_CLIENT_NAME);
        $clientEntity->setRedirectUri('');
        $clientEntity->setIsConfidential(false);

        return $clientEntity;
    }

    protected static function seedUserRepository(
        string $userId = self::TEST_USER_ID, 
        string $email = self::TEST_EMAIL, 
        string $displayName = self::TEST_USER_DISPLAY_NAME, 
        array $additionalData = [],
    ): void
    {
        $values = self::formatValues([
            $userId,
            $email,
            $displayName,
            json_encode($additionalData),
        ]);
        self::$pdo->exec(
            "INSERT INTO users (
                id, email, display_name, additional_data
            ) VALUES (
                " . $values . "
            )"
        );
    }

    protected function setUp(): void
    {
        // Reset the database before each test
        self::$migrationRunner->reset('testing');
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        self::$pdo->exec('TRUNCATE TABLE oauth_access_tokens');
        self::$pdo->exec('TRUNCATE TABLE oauth_refresh_tokens');
        self::$pdo->exec('TRUNCATE TABLE oauth_client_scopes');
        self::$pdo->exec('TRUNCATE TABLE oauth_scopes');
        self::$pdo->exec('TRUNCATE TABLE oauth_clients');
        self::$pdo->exec('TRUNCATE TABLE email_tokens');
        self::$pdo->exec('TRUNCATE TABLE users');
        self::$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected static function formatValues(array $values): string
    {
        return implode(', ', array_map(function ($value) {
            return "'" . $value . "'";
        }, $values));
    }
}
