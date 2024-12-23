<?php

declare(strict_types=1);

namespace Tests\Unit\Interactor;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use SlopeIt\ClockMock\ClockMock;
use Zestic\GraphQL\AuthComponent\Interactor\RequestAccessToken;

class RequestAccessTokenTest extends TestCase
{
    private AuthorizationServer $authServer;
    private RequestAccessToken $requestAccessToken;

    protected function setUp(): void
    {
        $this->authServer = $this->createMock(AuthorizationServer::class);
        $this->requestAccessToken = new RequestAccessToken($this->authServer);
        ClockMock::freeze(new \DateTime('2024-06-05'));
    }

    protected function tearDown(): void
    {
        ClockMock::reset();
        parent::tearDown();
    }

    public function testExecuteSuccessfully(): void
    {
        $refreshToken = 'refresh_token_identifier';
        $clientId = 'client_id';
        $clientSecret = 'client_secret';

        $response = $this->createMock(ResponseInterface::class);
        $stream = $this->createMock(StreamInterface::class);

        $this->authServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($response);

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn(json_encode([
                'access_token' => 'new_access_token',
                'refresh_token' => $refreshToken,
                'expires_at' => 3600,
            ]));

        $result = $this->requestAccessToken->execute($refreshToken, $clientId, $clientSecret);

        $this->assertArrayHasKey('accessToken', $result);
        $this->assertArrayHasKey('refreshToken', $result);

        $this->assertEquals('new_access_token', $result['accessToken']);
        $this->assertEquals($refreshToken, $result['refreshToken']);
    }

    public function testExecuteThrowsOAuthServerException(): void
    {
        $refreshToken = 'refresh_token';
        $clientId = 'client_id';
        $clientSecret = 'client_secret';

        $serverRequest = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

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
