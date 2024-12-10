<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function create(RegistrationContext $context): string|int
    {
        $stmt = $this->pdo->prepare(
        "INSERT INTO users (email, display_name, additional_data, status)
            VALUES (:email, :display_name, :additional_data, :status)"
        );
        $displayName = $context->extractAndRemove('displayName');
        try {
            $stmt->execute([
                'email'           => $context->email,
                'display_name'    => $displayName,
                'additional_data' => json_encode($context->data),
                'status'          => 'unverified',
            ]);

            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \RuntimeException('Failed to create user', 0, $e);
        }
    }

    public function findUserByEmail(string $email): ?UserInterface
    {
        $stmt = $this->pdo->prepare(
            "SELECT additional_data, email, display_name, id, status
            FROM users
            WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);
        $userData = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$userData) {
            return null;
        }

        return new User(
            json_decode($userData['additional_data'], true),
            $userData['display_name'],
            $userData['email'],
            $userData['id'],
            $userData['status'],
        );
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare(
        "SELECT COUNT(*) FROM users WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);

        return (int)$stmt->fetchColumn() > 0;
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
