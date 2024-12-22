<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\RefreshTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
        private TokenConfig $tokenConfig,
    ) {
    }

    public function getNewRefreshToken(): RefreshTokenEntity
    {
        $refreshTokenEntity = new RefreshTokenEntity();
        $refreshTokenEntity->setExpiryDateTime($this->tokenConfig->getRefreshTokenTTLDateTime());

        return $refreshTokenEntity;
    }

    public function persistNewRefreshToken(RefreshTokenEntity|RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO oauth_refresh_tokens (id, access_token_id, client_id, revoked, user_id, expires_at)
                VALUES (:id, :access_token_id, :client_id, :revoked, :user_id, :expires_at)
            ");
            $result = $stmt->execute([
                'id' => $refreshTokenEntity->getIdentifier(),
                'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
                'client_id' => $refreshTokenEntity->getClientIdentifier(),
                'revoked' => 0,
                'user_id' => $refreshTokenEntity->getUserIdentifier(),
                'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
            ]);

            if ($result === false) {
                throw new UniqueTokenIdentifierConstraintViolationException('Could not persist new refresh token');
            }
        } catch (\Exception $exception) {
            throw new UniqueTokenIdentifierConstraintViolationException('Could not persist new refresh token');
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE oauth_refresh_tokens
            SET revoked = 1
            WHERE id = :id
        ");

        $stmt->execute(['id' => $tokenId]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT revoked
            FROM oauth_refresh_tokens
            WHERE id = :id
        ");

        $stmt->execute(['id' => $tokenId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result === false || $result['revoked'];
    }
}
