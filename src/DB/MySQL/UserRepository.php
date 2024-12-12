<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface as OAuth2UserInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface as OAuth2UserRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface, OAuth2UserRepositoryInterface
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
        "INSERT INTO users (email, display_name, additional_data, verified_at)
            VALUES (:email, :display_name, :additional_data, :verified_at)"
        );
        $displayName = $context->extractAndRemove('displayName');
        try {
            $stmt->execute([
                'email'           => $context->email,
                'display_name'    => $displayName,
                'additional_data' => json_encode($context->data),
                'verified_at'     => null,
            ]);

            return $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            throw new \RuntimeException('Failed to create user', 0, $e);
        }
    }

    public function findUserByEmail(string $email): ?UserInterface
    {
        $stmt = $this->pdo->prepare(
            "SELECT additional_data, email, display_name, id, verified_at
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
            $userData['verified_at'] ? new \DateTimeImmutable($userData['verified_at']) : null,
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

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?OAuth2UserInterface {
        return $this->findUserByEmail($username);
    }

    public function rollback(): void
    {
        $this->pdo->rollBack();
    }
}
