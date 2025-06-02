<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException;
use Zestic\GraphQL\AuthComponent\Entity\RefreshTokenEntity;
use Zestic\GraphQL\AuthComponent\Entity\TokenConfig;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;

class RefreshTokenRepository extends AbstractPDORepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
        private TokenConfig $tokenConfig,
    ) {
        parent::__construct($pdo);
    }

    public function getNewRefreshToken(): RefreshTokenEntity
    {
        $refreshTokenEntity = new RefreshTokenEntity();
        /** @var non-empty-string $identifier */
        $identifier = $this->generateUniqueIdentifier();
        $refreshTokenEntity->setIdentifier($identifier);
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
                INSERT INTO {$this->schema}oauth_refresh_tokens (id, access_token_id, client_id, revoked, user_id, expires_at)
                VALUES (:id, :access_token_id, :client_id, :revoked, :user_id, :expires_at)
            ");
            $result = $stmt->execute([
                'access_token_id' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
                'client_id' => $refreshTokenEntity->getClientIdentifier(),
                'expires_at' => $refreshTokenEntity->getExpiryDateTime()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'id' => $refreshTokenEntity->getIdentifier(),
                'revoked' => $this->dbBool(false),
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
            FROM {$this->schema}oauth_refresh_tokens
            WHERE access_token_id = :access_token_id
        ");

        $stmt->execute(['access_token_id' => $accessTokenId]);
        $data = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return array_map(fn (array $data) => $this->hydrateRefreshToken($data), $data);
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->schema}oauth_refresh_tokens
            SET revoked = :revoked
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $tokenId,
            'revoked' => $this->isPgsql ? true : 1,
        ]);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT revoked
            FROM {$this->schema}oauth_refresh_tokens
            WHERE id = :id
        ");

        $stmt->execute(['id' => $tokenId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $result === false || $result['revoked'];
    }

    public function hydrateRefreshToken(array $data): RefreshTokenEntity
    {
        $token = new RefreshTokenEntity();
        $token->setIdentifier($data['id']);
        $token->setAccessToken($data['access_token_id']);
        $token->setClientIdentifier($data['client_id']);
        $token->setUserIdentifier($data['user_id']);
        $token->setExpiryDateTime(new \DateTimeImmutable($data['expires_at']));

        return $token;
    }
}
