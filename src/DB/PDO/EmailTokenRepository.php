<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;
use Zestic\GraphQL\AuthComponent\Repository\EmailTokenRepositoryInterface;

class EmailTokenRepository extends AbstractPDORepository implements EmailTokenRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
    ) {
        parent::__construct($pdo);
    }

    public function create(EmailToken $emailToken): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO graphql_auth_test.email_tokens (expiration, token, token_type, user_id)
            VALUES (:expiration, :token, :token_type, :user_id)
        ");

        return $stmt->execute([
            'expiration' => $emailToken->expiration->format('Y-m-d H:i:s'),
            'token' => $emailToken->token,
            'token_type' => $emailToken->tokenType->value,
            'user_id' => $emailToken->userId,
        ]);
    }

    public function delete(EmailToken|string $emailToken): bool
    {
        $token = $emailToken instanceof EmailToken ? $emailToken->token : $emailToken;

        $stmt = $this->pdo->prepare("
            DELETE FROM graphql_auth_test.email_tokens
            WHERE token = :token
        ");

        return $stmt->execute(['token' => $token]);
    }

    public function findByToken(string $token): ?EmailToken
    {
        $stmt = $this->pdo->prepare("
            SELECT id, expiration, token, token_type, user_id
            FROM graphql_auth_test.email_tokens
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
