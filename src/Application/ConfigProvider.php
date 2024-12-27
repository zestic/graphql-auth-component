<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent;

use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\Application\Factory\AuthPDOFactory;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => $this->getDependencies(),
        ];
    }

    private function getDependencies(): array
    {
        return [
            'factories' => [
                AuthPDO::class => AuthPDOFactory::class,
            ],
        ];
    }
}
