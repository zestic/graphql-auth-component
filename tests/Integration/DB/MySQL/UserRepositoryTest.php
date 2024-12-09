<?php

namespace Tests\Integration\DB\MySQL;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\DB\MySQL\UserRepository;
use Tests\Integration\DatabaseTestCase;

class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepository = new UserRepository(self::$pdo);
    }

    public function testCreateUser()
    {
        $email = 'testCreate@zestic.com';
        $displayName = 'Test User';
        $additionalData = ['displayName' => $displayName, 'referredById' => 2345];

        $context = new RegistrationContext($email, $additionalData);

        $userId = $this->userRepository->create($context);

        $this->assertNotEmpty($userId);
        $this->assertIsString($userId);

        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($user);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($displayName, $user['display_name']);
        $this->assertEquals($context->data, json_decode($user['additional_data'], true));
    }

    public function testCreateUserEmptyAdditionalData()
    {
        $email = 'testEmptyData@zestic.com';
        $displayName = 'Test User';
        $context = new RegistrationContext($email, ['displayName' => $displayName]);

        $userId = $this->userRepository->create($context);

        $this->assertNotEmpty($userId);
        $this->assertIsString($userId);

        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($user);
        $this->assertEquals($email, $user['email']);
        $this->assertEquals($displayName, $user['display_name']);
        $this->assertEquals(json_encode([]), $user['additional_data']);
    }

    public function testEmailExists()
    {
        $email = 'existing@zestic.com';
        $context = new RegistrationContext($email, ['displayName' => 'Test User']);
        $this->userRepository->create($context);

        $this->assertTrue($this->userRepository->emailExists($email));
        $this->assertFalse($this->userRepository->emailExists('nonexistent@zestic.com'));
    }

    public function testFindUserByEmail()
    {
        $email = 'testFind@zestic.com';
        $displayName = 'Find Test User';
        $additionalData = ['displayName' => $displayName, 'age' => 30];

        $context = new RegistrationContext($email, $additionalData);
        $userId = $this->userRepository->create($context);

        $foundUser = $this->userRepository->findUserByEmail($email);

        $this->assertNotNull($foundUser);
        $this->assertEquals($userId, $foundUser->getId());
        $this->assertEquals($email, $foundUser->getEmail());
        $this->assertEquals($displayName, $foundUser->displayName);
        $this->assertEquals(30, $foundUser->additionalData['age']);
        $this->assertEquals('unverified', $foundUser->status);

        // Test for non-existent email
        $nonExistentUser = $this->userRepository->findUserByEmail('nonexistent@zestic.com');
        $this->assertNull($nonExistentUser);
    }
    public function testTransactionMethods()
    {
        // Test successful transaction
        $this->userRepository->beginTransaction();

        $email = 'transaction_test@zestic.com';
        $context = new RegistrationContext($email, ['displayName' => 'Transaction Test']);
        $userId = $this->userRepository->create($context);

        $this->userRepository->commit();

        $foundUser = $this->userRepository->findUserByEmail($email);
        $this->assertNotNull($foundUser);
        $this->assertEquals($email, $foundUser->getEmail());

        // Test transaction rollback
        $this->userRepository->beginTransaction();

        $rollbackEmail = 'rollback_test@zestic.com';
        $rollbackContext = new RegistrationContext($rollbackEmail, ['displayName' => 'Rollback Test']);
        $this->userRepository->create($rollbackContext);

        // Verify the user exists before rollback
        $userBeforeRollback = $this->userRepository->findUserByEmail($rollbackEmail);
        $this->assertNotNull($userBeforeRollback);

        $this->userRepository->rollback();

        // Verify the user doesn't exist after rollback
        $userAfterRollback = $this->userRepository->findUserByEmail($rollbackEmail);
        $this->assertNull($userAfterRollback);

        // Test that operations outside of a transaction are committed automatically
        $autoCommitEmail = 'auto_commit@zestic.com';
        $autoCommitContext = new RegistrationContext($autoCommitEmail, ['displayName' => 'Auto Commit Test']);
        $this->userRepository->create($autoCommitContext);

        $autoCommitUser = $this->userRepository->findUserByEmail($autoCommitEmail);
        $this->assertNotNull($autoCommitUser);
        $this->assertEquals($autoCommitEmail, $autoCommitUser->getEmail());
    }
}
