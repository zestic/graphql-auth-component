<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
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
            INSERT INTO email_tokens (expiration, token, token_type, user_agent, user_id)
            VALUES (:expiration, :token, :token_type, :user_agent, :user_id)
        ");

        return $stmt->execute([
            'expiration' => $emailToken->expiration->format('Y-m-d H:i:s'),
            'token' => $emailToken->token,
            'token_type' => $emailToken->tokenType->value,
            'user_agent' => json_encode($emailToken->userAgent),
            'user_id' => $emailToken->userId,
        ]);
    }
}
