<?php

namespace Tests\Integration;

use \PDO;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\DB\MigrationRunner;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

abstract class DatabaseTestCase extends TestCase
{
    const string TEST_CLIENT_ID = 'test_client';
    const string TEST_CLIENT_NAME = 'Test Client';
    const string TEST_CLIENT_SECRET = 'test_secret';

    protected static MigrationRunner $migrationRunner;
    protected static ?PDO $pdo;
    protected static TokenConfig $tokenConfig;

    public static function setUpBeforeClass(): void
    {
        $host = $_ENV['TEST_DB_HOST'];
        $dbname = $_ENV['TEST_DB_NAME'];
        $username = $_ENV['TEST_DB_USER'];
        $password = $_ENV['TEST_DB_PASS'];
        $port = $_ENV['TEST_DB_PORT'];

        $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
        self::$pdo = new PDO($dsn, $username, $password);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::$tokenConfig = new TokenConfig(
            1,
            1,
            1,
            1,
        );
        // Initialize MigrationRunner
        self::$migrationRunner = new MigrationRunner();

        // Run migrations to set up the database schema
        self::$migrationRunner->migrate('testing');
    }

    public static function tearDownAfterClass(): void
    {
        // Rollback all migrations
        self::$migrationRunner->rollback('testing', '0');

        self::$pdo = null;
    }

    protected function seedDatabase(): void
    {
        $this->seedClientRepository();
    }

    protected function seedClientRepository(): void
    {
        self::$pdo->exec(
            "INSERT INTO oauth_clients (client_id, name) VALUES ('" . self::TEST_CLIENT_ID . "', '" . self::TEST_CLIENT_NAME . "')"
        );
    }

    protected function setUp(): void
    {
        // Optionally, you can reset the database before each test
        // self::$migrationRunner->reset('testing');
    }

    protected function tearDown(): void
    {
        // Clean up test data after each test if needed
        // This depends on your specific needs. You might want to truncate tables instead of dropping them.
        // For example:
        // self::$pdo->exec("TRUNCATE TABLE users");
    }
}
