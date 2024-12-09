<?php

declare(strict_types=1);

namespace Zestic\GraphQL\AuthComponent\Factory;

use DeviceDetector\DeviceDetector;
use Zestic\GraphQL\AuthComponent\Entity\EmailToken;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenConfig;
use Zestic\GraphQL\AuthComponent\Entity\EmailTokenType;

class EmailTokenFactory
{
    public function __construct(
        private EmailTokenConfig $config,
    ) {
    }

    public function createRegistrationToken(string $userId): EmailToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getRegistrationTimeOfLifeMinutes()} minutes");

        return new EmailToken(
            $expiration,
            bin2hex(random_bytes(16)),
            EmailTokenType::REGISTRATION,
            $this->gatherUserAgent(),
            $userId,
        );
    }

    public function createLoginToken(string $userId): EmailToken
    {
        $expiration = new \DateTime();
        $expiration->modify("+{$this->config->getLoginTimeOfLifeMinutes()} minutes");

        return new EmailToken(
            $expiration,
            bin2hex(random_bytes(16)),
            EmailTokenType::LOGIN,
            $this->gatherUserAgent(),
            $userId,
        );
    }

    private function gatherUserAgent(): array
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'];
        $dd = new DeviceDetector($userAgent);
        $dd->parse();

        return [
            'browser'        => $dd->getClient('name'),
            'browserVersion' => $dd->getClient('version'),
            'os'             => $dd->getOs('name'),
            'osVersion'      => $dd->getOs('version'),
            'device'         => $dd->getDeviceName(),
            'brand'          => $dd->getBrandName(),
            'model'          => $dd->getModel(),
            'ipAddress'      => $this->getClientIpAddress(),
        ];
    }

    private function getClientIpAddress(): string
    {
        $ipAddress = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ipAddress = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            $ipAddress = $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ipAddress = $_SERVER['REMOTE_ADDR'];
        }

        // If the IP address is a comma-separated list, take the first one
        if (strpos($ipAddress, ',') !== false) {
            $ipAddress = explode(',', $ipAddress)[0];
        }

        return $ipAddress;
    }
}
