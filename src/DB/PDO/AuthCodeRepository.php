<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use Carbon\CarbonImmutable;
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
            INSERT INTO {$this->schema}oauth_auth_codes (id, user_id, client_id, scopes, redirect_uri, code_challenge, code_challenge_method, revoked, expires_at)
            VALUES (:id, :user_id, :client_id, :scopes, :redirect_uri, :code_challenge, :code_challenge_method, :revoked, :expires_at)
        ");

        // Convert scopes to array of identifiers
        $scopes = [];
        foreach ($authCodeEntity->getScopes() as $scope) {
            $scopes[] = $scope->getIdentifier();
        }

        // Get PKCE data if available (custom property on our AuthCodeEntity)
        $codeChallenge = null;
        $codeChallengeMethod = null;
        if ($authCodeEntity instanceof \Zestic\GraphQL\AuthComponent\Entity\AuthCodeEntity) {
            $codeChallenge = $authCodeEntity->getCodeChallenge();
            $codeChallengeMethod = $authCodeEntity->getCodeChallengeMethod();
        }

        $stmt->execute([
            'id' => $authCodeEntity->getIdentifier(),
            'user_id' => $authCodeEntity->getUserIdentifier(),
            'client_id' => $authCodeEntity->getClient()->getIdentifier(),
            'scopes' => json_encode($scopes),
            'redirect_uri' => $authCodeEntity->getRedirectUri(),
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
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

        if (! $result) {
            return true; // If not found, consider it revoked
        }

        return (bool) $result['revoked'];
    }

    public function getAuthCodeEntityByIdentifier(string $identifier): ?AuthCodeEntityInterface
    {
        // Debug logging
        error_log("AuthCodeRepository::getAuthCodeEntityByIdentifier called with identifier: {$identifier}");

        $stmt = $this->pdo->prepare("
            SELECT ac.*, c.name as client_name, c.redirect_uri as client_redirect_uri
            FROM {$this->schema}oauth_auth_codes ac
            JOIN {$this->schema}oauth_clients c ON ac.client_id = c.client_id
            WHERE ac.id = :id AND ac.revoked = :revoked AND ac.expires_at > NOW()
        ");

        $stmt->execute([
            'id' => $identifier,
            'revoked' => $this->dbBool(false),
        ]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            error_log("AuthCodeRepository::getAuthCodeEntityByIdentifier - No result found for identifier: {$identifier}");

            return null;
        }

        error_log("AuthCodeRepository::getAuthCodeEntityByIdentifier - Found auth code: " . json_encode($result));

        // Create and populate the auth code entity
        $authCode = new AuthCodeEntity();
        $authCode->setIdentifier($result['id']);
        $authCode->setUserIdentifier($result['user_id']);
        $authCode->setExpiryDateTime(new CarbonImmutable($result['expires_at']));
        $authCode->setRedirectUri($result['redirect_uri']);

        // Set PKCE data
        if ($result['code_challenge']) {
            $authCode->setCodeChallenge($result['code_challenge']);
        }
        if ($result['code_challenge_method']) {
            $authCode->setCodeChallengeMethod($result['code_challenge_method']);
        }

        // Create and set client entity
        $client = new \Zestic\GraphQL\AuthComponent\Entity\ClientEntity();
        $client->setIdentifier($result['client_id']);
        $client->setName($result['client_name']);
        $client->setRedirectUri($result['client_redirect_uri']);
        $authCode->setClient($client);

        // Set scopes
        if ($result['scopes']) {
            $scopes = json_decode($result['scopes'], true);
            foreach ($scopes as $scopeIdentifier) {
                $scope = new \Zestic\GraphQL\AuthComponent\Entity\ScopeEntity($scopeIdentifier);
                $authCode->addScope($scope);
            }
        }

        return $authCode;
    }

    /**
     * Generate a unique identifier for auth codes (UUID format)
     */
    public function generateUniqueIdentifier(int $length = 40): string
    {
        // Generate a proper UUID v4 since the database expects UUID format
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Set bits 6-7 to 10

        return sprintf(
            '%08s-%04s-%04s-%04s-%12s',
            bin2hex(substr($data, 0, 4)),
            bin2hex(substr($data, 4, 2)),
            bin2hex(substr($data, 6, 2)),
            bin2hex(substr($data, 8, 2)),
            bin2hex(substr($data, 10, 6))
        );
    }
}
