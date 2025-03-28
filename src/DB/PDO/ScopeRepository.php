<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\DB\PDO;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\ScopeEntity;

class ScopeRepository extends AbstractPDORepository implements ScopeRepositoryInterface
{
    public function __construct(
        \PDO $pdo,
    ) {
        parent::__construct($pdo);
    }

    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        $stmt = $this->pdo->prepare('SELECT * FROM ' . $this->schema . 'oauth_scopes WHERE id = :identifier');
        $stmt->execute(['identifier' => $identifier]);

        $scope = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($scope === false) {
            return null;
        }

        return new ScopeEntity($scope['id'], $scope['description']);
    }

    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        ?string $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        if (empty($scopes)) {
            return $scopes;
        }
        // Retrieve allowed scopes for the client
        $stmt = $this->pdo->prepare('SELECT scope FROM ' . $this->schema . 'oauth_client_scopes WHERE client_id = :clientId');
        $stmt->execute(['clientId' => $clientEntity->getIdentifier()]);
        $allowedScopes = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        // Filter scopes based on what's allowed for the client
        return array_filter($scopes, function (ScopeEntityInterface $scope) use ($allowedScopes) {
            return in_array($scope->getIdentifier(), $allowedScopes);
        });
    }
}
