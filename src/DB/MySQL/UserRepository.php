<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function create(RegistrationContext $context): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO users (email, display_name, additional_data)
            VALUES (:email, :display_name, :additional_data)
        ");

        $displayName = $context->extractAndRemove('displayName');

        try {
            return $stmt->execute([
                'email' => $context->email,
                'display_name' => $displayName,
                'additional_data' => json_encode($context->data),
            ]);
        } catch (\PDOException $e) {
            throw new \RuntimeException('Failed to create user', 0, $e);
        }
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM users WHERE email = :email
        ");

        $stmt->execute(['email' => $email]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
