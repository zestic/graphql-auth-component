<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private TokenConfig $tokenConfig,
    ) {
    }

    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): AccessTokenEntityInterface
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        $accessToken->setUserIdentifier($userIdentifier);
        $accessToken->setExpiryDateTime($this->tokenConfig->getAccessTokenTTLDateTime());

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO oauth_access_tokens (id, user_id, client_id, scopes, revoked, expires_at)
            VALUES (:id, :user_id, :client_id, :scopes, :revoked, :expires_at)
        ");

        $stmt->execute([
            'id' => $accessTokenEntity->getIdentifier(),
            'user_id' => $accessTokenEntity->getUserIdentifier(),
            'client_id' => $accessTokenEntity->getClient()->getIdentifier(),
            'scopes' => json_encode($accessTokenEntity->getScopes()),
            'revoked' => 0,
            'expires_at' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);

        if ($stmt->rowCount() === 0) {
            throw new UniqueTokenIdentifierConstraintViolationException('Could not persist access token');
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE oauth_access_tokens
            SET revoked = 1
            WHERE id = :id
        ");

        $stmt->execute(['id' => $tokenId]);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT revoked
            FROM oauth_access_tokens
            WHERE id = :id
        ");

        $stmt->execute(['id' => $tokenId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result === false || $result['revoked'];
    }
}
