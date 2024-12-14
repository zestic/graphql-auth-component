<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Zestic\GraphQL\AuthComponent\Entity\AccessTokenEntity;
use Zestic\GraphQL\AuthComponent\Interactor\RequestAccessToken;

class RequestAccessTokenTest extends TestCase
{
    private AuthorizationServer $authServer;
    private Psr17Factory $psr17Factory;
    private RequestAccessToken $requestAccessToken;

    protected function setUp(): void
    {
        $this->authServer = $this->createMock(AuthorizationServer::class);
        $this->psr17Factory = $this->createMock(Psr17Factory::class);
        $this->requestAccessToken = new RequestAccessToken($this->authServer, $this->psr17Factory);
    }

    public function testExecuteSuccessfully(): void
    {
        $refreshToken = 'refresh_token';
        $clientId = 'client_id';
        $clientSecret = 'client_secret';

        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->psr17Factory->expects($this->once())
            ->method('createServerRequest')
            ->with('POST', 'http://example.com/token')
            ->willReturn($serverRequest);

        $serverRequest->expects($this->once())
            ->method('withParsedBody')
            ->with([
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ])
            ->willReturnSelf();

        $this->psr17Factory->expects($this->once())
            ->method('createResponse')
            ->willReturn($response);

        $this->authServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->with($serverRequest, $response)
            ->willReturn($response);

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn(json_encode([
                'access_token' => 'new_access_token',
                'refresh_token' => 'new_refresh_token',
                'expires_in' => 3600,
            ]));

        $result = $this->requestAccessToken->execute($refreshToken, $clientId, $clientSecret);

        $this->assertInstanceOf(AccessTokenEntity::class, $result);
        $this->assertEquals('new_access_token', $result->accessToken);
        $this->assertEquals('new_refresh_token', $result->refreshToken);
        $this->assertEquals(3600, $result->expiresIn);
    }

    public function testExecuteThrowsOAuthServerException(): void
    {
        $refreshToken = 'refresh_token';
        $clientId = 'client_id';
        $clientSecret = 'client_secret';

        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        $this->psr17Factory->method('createServerRequest')->willReturn($serverRequest);
        $this->psr17Factory->method('createResponse')->willReturn($response);

        $serverRequest->method('withParsedBody')->willReturnSelf();

        $oauthException = OAuthServerException::invalidRefreshToken();

        $this->authServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willThrowException($oauthException);

        $this->expectException(OAuthServerException::class);
        $this->expectExceptionMessage('The refresh token is invalid.');

        $this->requestAccessToken->execute($refreshToken, $clientId, $clientSecret);
    }
}
