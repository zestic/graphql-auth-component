<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class EmailTokenRepository implements EmailTokenRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function create(EmailToken $emailToken): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO email_tokens (expiration, token, token_type, user_id)
            VALUES (:expiration, :token, :token_type, :user_id)
        ");

        return $stmt->execute([
            'expiration' => $emailToken->expiration->format('Y-m-d H:i:s'),
            'token' => $emailToken->token,
            'token_type' => $emailToken->tokenType->value,
            'user_id' => $emailToken->userId,
        ]);
    }

    public function findByToken(string $token): ?EmailToken
    {
        $stmt = $this->pdo->prepare("
            SELECT id, expiration, token, token_type, user_id
            FROM email_tokens
            WHERE token = :token
            AND expiration > NOW()
            LIMIT 1
        ");

        $stmt->execute(['token' => $token]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return new EmailToken(
            new \DateTimeImmutable($result['expiration']),
            $result['token'],
            EmailTokenType::from($result['token_type']),
            $result['user_id'],
            (string) $result['id'],
        );
    }
}
