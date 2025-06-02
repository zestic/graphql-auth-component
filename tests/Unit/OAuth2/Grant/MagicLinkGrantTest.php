<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth2\Grant;

use DateInterval;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Zestic\GraphQL\AuthComponent\Entity\MagicLinkToken;
use Zestic\GraphQL\AuthComponent\Entity\UserInterface;
use Zestic\GraphQL\AuthComponent\OAuth2\Grant\MagicLinkGrant;
use Zestic\GraphQL\AuthComponent\Repository\MagicLinkTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\RefreshTokenRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;

class MagicLinkGrantTest extends TestCase
{
    private MagicLinkGrant $grant;
    private MagicLinkTokenRepositoryInterface $magicLinkTokenRepository;
    private RefreshTokenRepositoryInterface $refreshTokenRepository;
    private ClientRepositoryInterface $clientRepository;
    private AccessTokenRepositoryInterface $accessTokenRepository;
    private ScopeRepositoryInterface $scopeRepository;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        $this->magicLinkTokenRepository = $this->createMock(MagicLinkTokenRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->clientRepository = $this->createMock(ClientRepositoryInterface::class);
        $this->accessTokenRepository = $this->createMock(AccessTokenRepositoryInterface::class);
        $this->scopeRepository = $this->createMock(ScopeRepositoryInterface::class);

        $this->grant = new MagicLinkGrant(
            $this->magicLinkTokenRepository,
            $this->refreshTokenRepository,
            $this->userRepository
        );
        $this->grant->setClientRepository($this->clientRepository);
        $this->grant->setAccessTokenRepository($this->accessTokenRepository);
        $this->grant->setScopeRepository($this->scopeRepository);

        $this->grant->setPrivateKey(new CryptKey(getcwd() . '/tests/resources/jwt/private.key'));
    }

    public function testGetIdentifier(): void
    {
        $this->assertEquals('magic_link', $this->grant->getIdentifier());
    }

    public function testRespondToAccessTokenRequest(): void
    {
        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $userEntity = $this->createMock(UserInterface::class);
        $scopeEntity = $this->createMock(ScopeEntityInterface::class);
        $accessTokenEntity = $this->createMock(AccessTokenEntityInterface::class);
        $refreshTokenEntity = $this->createMock(RefreshTokenEntityInterface::class);
        $magicLinkToken = $this->createMock(MagicLinkToken::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn([
            'token' => 'valid_token',
            'client_id' => 'location_1',
        ]);

        $responseType = $this->createMock(ResponseTypeInterface::class);

        $this->clientRepository->method('getClientEntity')->willReturn($clientEntity);
        $this->clientRepository->method('validateClient')->willReturn(true);
        $this->magicLinkTokenRepository->method('findByToken')->willReturn($magicLinkToken);
        $magicLinkToken->method('isExpired')->willReturn(false);
        $magicLinkToken->method('getUserId')->willReturn('user_id');

        $this->userRepository->method('findUserById')->willReturn($userEntity);
        $this->scopeRepository->method('finalizeScopes')->willReturn([$scopeEntity]);
        $this->accessTokenRepository->method('getNewToken')->willReturn($accessTokenEntity);
        $this->refreshTokenRepository->method('getNewRefreshToken')->willReturn($refreshTokenEntity);

        $responseType->expects($this->once())->method('setAccessToken');
        $responseType->expects($this->once())->method('setRefreshToken');

        $result = $this->grant->respondToAccessTokenRequest(
            $request,
            $responseType,
            new DateInterval('PT1H')
        );

        $this->assertInstanceOf(ResponseTypeInterface::class, $result);
    }

    public function testRespondToAccessTokenRequestWithInvalidToken(): void
    {
        $this->expectException(OAuthServerException::class);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn([
            'token' => 'invalid_token',
            'client_id' => 'location_1',
        ]);

        $responseType = $this->createMock(ResponseTypeInterface::class);

        $this->clientRepository->method('getClientEntity')->willReturn($clientEntity);
        $this->magicLinkTokenRepository->method('findByToken')->willReturn(null);
        $this->userRepository->method('findUserById')->willReturn(null);

        $this->grant->respondToAccessTokenRequest(
            $request,
            $responseType,
            new DateInterval('PT1H')
        );
    }

    public function testRespondToAccessTokenRequestWithExpiredToken(): void
    {
        $this->expectException(OAuthServerException::class);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $magicLinkToken = $this->createMock(MagicLinkToken::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn(['token' => 'expired_token']);

        $responseType = $this->createMock(ResponseTypeInterface::class);

        $this->clientRepository->method('getClientEntity')->willReturn($clientEntity);
        $this->magicLinkTokenRepository->method('findByToken')->willReturn($magicLinkToken);
        $magicLinkToken->method('isExpired')->willReturn(true);

        $this->grant->respondToAccessTokenRequest(
            $request,
            $responseType,
            new DateInterval('PT1H')
        );
    }

    public function testRespondToAccessTokenRequestWithNonExistentUser(): void
    {
        $this->expectException(OAuthServerException::class);

        $clientEntity = $this->createMock(ClientEntityInterface::class);
        $magicLinkToken = $this->createMock(MagicLinkToken::class);
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getParsedBody')->willReturn(['token' => 'valid_token']);

        $responseType = $this->createMock(ResponseTypeInterface::class);

        $this->clientRepository->method('getClientEntity')->willReturn($clientEntity);
        $this->magicLinkTokenRepository->method('findByToken')->willReturn($magicLinkToken);
        $magicLinkToken->method('isExpired')->willReturn(false);
        $magicLinkToken->method('getUserId')->willReturn('non_existent_user_id');

        $this->userRepository->method('findUserById')->willReturn(null);

        $this->grant->respondToAccessTokenRequest(
            $request,
            $responseType,
            new DateInterval('PT1H')
        );
    }
}
