<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\MySQL;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private \PDO $pdo,
    ) {
    }

    public function create(ClientEntityInterface $clientEntity): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO oauth_clients (client_id, name, redirect_uri, is_confidential)
            VALUES (:clientId, :name, :redirectUri, :isConfidential)
        ');

        return $stmt->execute([
            'clientId' => $clientEntity->getIdentifier(),
            'name' => $clientEntity->getName(),
            'redirectUri' => json_encode($clientEntity->getRedirectUri()),
            'isConfidential' => (int) $clientEntity->isConfidential(),
        ]);
    }

    public function delete(ClientEntityInterface $clientEntity): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE oauth_clients
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE client_id = :clientId AND deleted_at IS NULL
        ');

        return $stmt->execute([
            'clientId' => $clientEntity->getIdentifier(),
        ]);
    }

    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $stmt = $this->pdo->prepare('
        SELECT * FROM oauth_clients
        WHERE client_id = :clientId AND deleted_at IS NULL
    ');
        $stmt->execute(['clientId' => $clientIdentifier]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return null;
        }

        return new ClientEntity(
            $result['client_id'],
            $result['name'],
            $result['redirect_uri'] ? json_decode($result['redirect_uri'], true) : '',
            (bool) $result['is_confidential']
        );
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM oauth_clients
            WHERE client_id = :clientId AND deleted_at IS NULL
        ');
        $stmt->execute(['clientId' => $clientIdentifier]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$result) {
            return false;
        }

        if ($clientSecret && $result['is_confidential'] && !hash_equals($result['client_secret'], $clientSecret)) {
            return false;
        }

        // You might want to check if the client is allowed to use the specific grant type
        // This example assumes all grant types are allowed for all clients
        return true;
    }
}
