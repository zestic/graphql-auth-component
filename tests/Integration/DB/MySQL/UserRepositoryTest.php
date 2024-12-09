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

        $this->userRepository->create($context);

        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
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

        $this->userRepository->create($context);

        $stmt = self::$pdo->prepare("SELECT * FROM users WHERE email =?");
        $stmt->execute([$email]);
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
}
