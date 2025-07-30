<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Entity;

use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Entities\Traits\AuthCodeTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class AuthCodeEntity implements AuthCodeEntityInterface
{
    use AuthCodeTrait;
    use EntityTrait;
    use TokenEntityTrait;

    private ?string $codeChallenge = null;

    private ?string $codeChallengeMethod = null;

    public function getCodeChallenge(): ?string
    {
        return $this->codeChallenge;
    }

    public function setCodeChallenge(?string $codeChallenge): void
    {
        $this->codeChallenge = $codeChallenge;
    }

    public function getCodeChallengeMethod(): ?string
    {
        return $this->codeChallengeMethod;
    }

    public function setCodeChallengeMethod(?string $codeChallengeMethod): void
    {
        $this->codeChallengeMethod = $codeChallengeMethod;
    }
}
