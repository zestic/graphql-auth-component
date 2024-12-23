<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

trait TokenScopesTrait
{
    public function setScopesFromArray(array $scopes): void
    {
        foreach ($scopes as $data) {
            $scope = new ScopeEntity($data['identifier'], $data['description']);
            $this->addScope($scope);
        }
    }

    public function getScopesAsArray(): array
    {
        $scopes = [];
        foreach ($this->scopes as $scope) {
            $scopes[] = [
                'identifier' => $scope->getIdentifier(),
                'description' => $scope->getDescription(),
            ];
        }

        return $scopes;
    }
}