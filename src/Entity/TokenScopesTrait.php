<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

trait TokenScopesTrait
{
    /**
     * @param array<string|array{identifier: string, description?: string}> $scopes
     */
    public function setScopesFromArray(array $scopes): void
    {
        foreach ($scopes as $data) {
            if (is_string($data)) {
                $scope = new ScopeEntity($data, '');
            } else {
                $scope = new ScopeEntity($data['identifier'], $data['description'] ?? '');
            }
            $this->addScope($scope);
        }
    }

    /**
     * @return array<string>
     */
    public function getScopesAsArray(): array
    {
        $scopes = [];
        foreach ($this->getScopes() as $scope) {
            $scopes[] = $scope->getIdentifier();
        }
        return $scopes;
    }
}