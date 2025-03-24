<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

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

    /**
     * @param RefreshTokenEntity $refreshTokenEntity
     */
    public function persistNewRefreshToken(RefreshTokenEntity|RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO oauth_refresh_tokens (id, access_token_id, client_id, revoked, user_id, expires_at)
                VALUES (:id, :access_token_id, :client_id, :revoked, :user_id, :expires_at)
            ");
            $result = $stmt->execute([
                'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
                'client_id' => $refreshTokenEntity->getClientIdentifier(),
                'expires_at' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
                'id' => $refreshTokenEntity->getIdentifier(),
                'revoked' => 0,
                'user_id' => $refreshTokenEntity->getUserIdentifier(),
            ]);

            if ($result === false) {
                throw UniqueTokenIdentifierConstraintViolationException::create();
            }
        } catch (\Exception $exception) {
            throw UniqueTokenIdentifierConstraintViolationException::create();
        }
    }

    public function findRefreshTokensByAccessTokenId(string $accessTokenId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM oauth_refresh_tokens
            WHERE access_token_id = :access_token_id
        ");

        $stmt->execute(['access_token_id' => $accessTokenId]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn(array $data) => $this->hydrateRefreshToken($data), $data);
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

    public function hydrateRefreshToken(array $data): RefreshTokenEntity
    {
        $token =  new RefreshTokenEntity();
        $token->setIdentifier($data['id']);
        $token->setAccessToken($data['access_token_id']);
        $token->setClientIdentifier($data['client_id']);
        $token->setUserIdentifier($data['user_id']);
        $token->setExpiryDateTime(new \DateTimeImmutable($data['expires_at']));

        return $token;
    }
}
