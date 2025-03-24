<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

interface TokenScopesInterface
{
    /**
     * @return array<string>
     */
    public function getScopesAsArray(): array;
}
