<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

class MagicLinkTokenRepository extends AbstractPDORepository implements MagicLinkTokenRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
    ) {
        parent::__construct($pdo);
    }

    public function create(MagicLinkToken $magicLinkToken): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->schema}magic_link_tokens (expiration, token, token_type, user_id)
            VALUES (:expiration, :token, :token_type, :user_id)
        ");

        return $stmt->execute([
            'expiration' => $magicLinkToken->expiration->format('Y-m-d H:i:s'),
            'token' => $magicLinkToken->token,
            'token_type' => $magicLinkToken->tokenType->value,
            'user_id' => $magicLinkToken->userId,
        ]);
    }

    public function delete(MagicLinkToken|string $magicLinkToken): bool
    {
        $token = $magicLinkToken instanceof MagicLinkToken ? $magicLinkToken->token : $magicLinkToken;

        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->schema}magic_link_tokens
            WHERE token = :token
        ");

        return $stmt->execute(['token' => $token]);
    }

    public function findByToken(string $token): ?MagicLinkToken
    {
        $stmt = $this->pdo->prepare("
            SELECT id, expiration, token, token_type, user_id
            FROM {$this->schema}magic_link_tokens
            WHERE token = :token
            AND expiration > NOW()
            LIMIT 1
        ");

        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return null;
        }

        return new MagicLinkToken(
            new \DateTimeImmutable($result['expiration']),
            $result['token'],
            MagicLinkTokenType::from($result['token_type']),
            $result['user_id'],
            (string) $result['id'],
        );
    }
}
