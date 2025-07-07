<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use Carbon\CarbonImmutable;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkTokenType;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;

class MagicLinkTokenRepository extends AbstractPDORepository implements MagicLinkTokenRepositoryInterface
{
    public function create(MagicLinkToken $magicLinkToken): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->schema}magic_link_tokens (
                client_id, code_challenge, code_challenge_method, redirect_uri, state, email,
                expiration, token, token_type, user_id, ip_address, user_agent
            )
            VALUES (
                :client_id, :code_challenge, :code_challenge_method, :redirect_uri, :state, :email,
                :expiration, :token, :token_type, :user_id, :ip_address, :user_agent
            )
        ");

        return $stmt->execute($this->dehydrateMagicLinkToken($magicLinkToken));
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

    public function findByToken(string $token, bool $unexpired = false): ?MagicLinkToken
    {
        $sql = <<<SQL
        SELECT id, client_id, code_challenge, code_challenge_method, redirect_uri, state, email,
               expiration, token, token_type, user_id, ip_address, user_agent
        FROM {$this->schema}magic_link_tokens
        WHERE token = :token
        SQL;
        if ($unexpired) {
            $sql .= " AND expiration > NOW()";
        }
        $sql .= " LIMIT 1";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute(['token' => $token]);
        if (! $result = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            return null;
        }

        return $this->hydrateMagicLinkToken($result);
    }

    public function findByUnexpiredToken(string $token): ?MagicLinkToken
    {
        return $this->findByToken($token, true);
    }

    private function dehydrateMagicLinkToken(MagicLinkToken $magicLinkToken): array
    {
        return [
            'client_id' => $magicLinkToken->clientId,
            'code_challenge' => $magicLinkToken->codeChallenge,
            'code_challenge_method' => $magicLinkToken->codeChallengeMethod,
            'redirect_uri' => $magicLinkToken->redirectUri,
            'state' => $magicLinkToken->state,
            'email' => $magicLinkToken->email,
            'expiration' => $magicLinkToken->expiration->format('Y-m-d H:i:s.u'),
            'token' => $magicLinkToken->token,
            'token_type' => $magicLinkToken->tokenType->value,
            'user_id' => $magicLinkToken->userId,
            'ip_address' => $magicLinkToken->ipAddress,
            'user_agent' => $magicLinkToken->userAgent,
        ];
    }

    private function hydrateMagicLinkToken(?array $data): ?MagicLinkToken
    {
        if (empty($data)) {
            return null;
        }

        $magicLinkToken = new MagicLinkToken(
            $data['client_id'],
            $data['code_challenge'],
            $data['code_challenge_method'],
            $data['redirect_uri'],
            $data['state'],
            $data['email'],
            new CarbonImmutable($data['expiration']),
            MagicLinkTokenType::from($data['token_type']),
            $data['user_id'],
            $data['ip_address'],
            $data['user_agent'],
            (string) $data['id'],
        );

        // Override the generated token with the one from the database
        $magicLinkToken->token = $data['token'];

        return $magicLinkToken;
    }
}
