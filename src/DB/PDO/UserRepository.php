<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use Carbon\CarbonImmutable;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface as OAuth2UserInterface;
use Zestic\GraphQL\AuthComponent\Context\RegistrationContext;
use Zestic\GraphQL\AuthComponent\Entity\User;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class UserRepository extends AbstractPDORepository implements UserRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
    ) {
        parent::__construct($pdo);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function create(RegistrationContext $context): string|int
    {
        $id = $this->generateUniqueIdentifier();
        $stmt = $this->pdo->prepare(
            'INSERT INTO ' . $this->schema . 'users (id, email, display_name, additional_data, verified_at)
            VALUES (:id, :email, :display_name, :additional_data, :verified_at)'
        );
        $additionalData = $context->get('additionalData');

        try {
            $stmt->execute([
                'id' => $id,
                'email' => $context->get('email'),
                'display_name' => $additionalData['displayName'],
                'additional_data' => json_encode($additionalData),
                'verified_at' => null,
            ]);

            return $id;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Failed to create user', 0, $e);
        }
    }

    public function findUserByEmail(string $email): ?UserInterface
    {
        return $this->findUserBy('email', $email);
    }

    public function findUserById(string $id): ?UserInterface
    {
        return $this->findUserBy('id', $id);
    }

    private function findUserBy(string $field, string $value): ?UserInterface
    {
        $stmt = $this->pdo->prepare(
            'SELECT additional_data, email, display_name, id, system_id, verified_at
            FROM ' . $this->schema . 'users
            WHERE ' . $field . ' = :' . $field
        );
        $stmt->execute([$field => $value]);
        $userData = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($userData === false) {
            return null;
        }

        return $this->hydrateUser($userData);
    }

    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM " . $this->schema . "users WHERE email = :email"
        );
        $stmt->execute(['email' => $email]);

        return ((int)$stmt->fetchColumn()) > 0;
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

    public function update(UserInterface $user): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE " . $this->schema . "users 
        SET email = :email, 
            display_name = :display_name, 
            additional_data = :additional_data, 
            system_id = :system_id,
            verified_at = :verified_at
        WHERE id = :id"
        );

        try {
            $result = $stmt->execute([
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'display_name' => $user->getDisplayName(),
                'additional_data' => json_encode($user->getAdditionalData()),
                'system_id' => $user->getSystemId(),
                'verified_at' => $user->getVerifiedAt()?->format('Y-m-d H:i:s'),
            ]);

            return $result && $stmt->rowCount() > 0;
        } catch (\PDOException $e) {
            throw new \RuntimeException('Failed to update user', 0, $e);
        }
    }

    protected function hydrateUser(array $userData): ?UserInterface
    {
        if (! $userData) {
            return null;
        }

        $user = new User(
            json_decode($userData['additional_data'], true),
            $userData['display_name'],
            $userData['email'],
            $userData['id'],
            $userData['verified_at'] ? new CarbonImmutable($userData['verified_at']) : null,
        );
        $user->setSystemId($userData['system_id'] ?? null);

        return $user;
    }
}
