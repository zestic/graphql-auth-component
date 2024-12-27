<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Application\Factory;

use Psr\Container\ContainerInterface;
use Zestic\GraphQL\AuthComponent\Application\DB\AuthPDO;
use Zestic\GraphQL\AuthComponent\DB\MySQL\EmailTokenRepository;

class EmailTokenRepositoryFactory
{
    public function __invoke(ContainerInterface $container): EmailTokenRepository
    {
        return new EmailTokenRepository(
            $container->get(AuthPDO::class)
        );
    }
}
