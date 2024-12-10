<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

interface UserInterface
{
    public function getId(): string|int;
    public function getEmail(): string;
}
