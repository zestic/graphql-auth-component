<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\AuthCodeEntity;

class AuthCodeRepository extends AbstractPDORepository implements AuthCodeRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
    ) {
        parent::__construct($pdo);
    }

    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->schema}oauth_auth_codes (id, user_id, client_id, scopes, redirect_uri, revoked, expires_at)
            VALUES (:id, :user_id, :client_id, :scopes, :redirect_uri, :revoked, :expires_at)
        ");

        // Convert scopes to array of identifiers
        $scopes = [];
        foreach ($authCodeEntity->getScopes() as $scope) {
            $scopes[] = $scope->getIdentifier();
        }

        $stmt->execute([
            'id' => $authCodeEntity->getIdentifier(),
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => json_encode($scopes),
            'redirect_uri' => $authCodeEntity->getRedirectUri(),
            'revoked' => $this->dbBool(false),
            'expires_at' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function revokeAuthCode(string $codeId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->schema}oauth_auth_codes
            SET revoked = :revoked
            WHERE id = :id
        ");

        $stmt->execute([
            'revoked' => $this->dbBool(true),
            'id' => $codeId,
        ]);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT revoked FROM {$this->schema}oauth_auth_codes
            WHERE id = :id
        ");

        $stmt->execute(['id' => $codeId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return true; // If not found, consider it revoked
        }

        return (bool) $result['revoked'];
    }

    /**
     * Generate a unique identifier for auth codes
     */
    public function generateUniqueIdentifier(int $length = 40): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }
}
