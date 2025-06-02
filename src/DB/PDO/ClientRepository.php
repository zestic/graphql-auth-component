<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;

class ClientRepository extends AbstractPDORepository implements ClientRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
    ) {
        parent::__construct($pdo);
    }

    public function create(ClientEntityInterface $clientEntity): bool
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->schema}oauth_clients (client_id, name, redirect_uri, is_confidential)
            VALUES (:clientId, :name, :redirectUri, :isConfidential)
        ");

        return $stmt->execute([
            'clientId' => $clientEntity->getIdentifier(),
            'name' => $clientEntity->getName(),
            'redirectUri' => json_encode($clientEntity->getRedirectUri()),
            'isConfidential' => (int) $clientEntity->isConfidential(),
        ]);
    }

    public function delete(ClientEntityInterface $clientEntity): bool
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->schema}oauth_clients
            SET deleted_at = CURRENT_TIMESTAMP
            WHERE client_id = :clientId AND deleted_at IS NULL
        ");

        return $stmt->execute([
            'clientId' => $clientEntity->getIdentifier(),
        ]);
    }

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $stmt = $this->pdo->prepare("
        SELECT * FROM {$this->schema}oauth_clients
        WHERE client_id = :clientId AND deleted_at IS NULL
    ");
        $stmt->execute(['clientId' => $clientIdentifier]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return null;
        }

        $clientEntity = new ClientEntity();
        $clientEntity->setIdentifier($result['client_id']);
        $clientEntity->setName($result['name']);
        $clientEntity->setRedirectUri($result['redirect_uri'] ? json_decode($result['redirect_uri'], true) : '');
        $clientEntity->setIsConfidential((bool) $result['is_confidential']);

        return $clientEntity;
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM {$this->schema}oauth_clients
            WHERE client_id = :clientId AND deleted_at IS NULL
        ");
        $stmt->execute(['clientId' => $clientIdentifier]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (! $result) {
            return false;
        }

        if ($clientSecret && $result['is_confidential'] && ! hash_equals($result['client_secret'], $clientSecret)) {
            return false;
        }

        // You might want to check if the client is allowed to use the specific grant type
        // This example assumes all grant types are allowed for all clients
        return true;
    }
}
