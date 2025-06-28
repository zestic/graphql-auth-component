<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handler;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Application\Handler\TokenRequestHandler;

class TokenRequestHandlerTest extends TestCase
{
    private TokenRequestHandler $handler;

    private AuthorizationServer $authorizationServer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->authorizationServer = $this->createMock(AuthorizationServer::class);
        $this->handler = new TokenRequestHandler($this->authorizationServer);
    }

    public function testHandleSuccessfulTokenRequest(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'auth-code-123',
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
                'redirect_uri' => 'https://example.com/callback',
            ]);

        $expectedResponse = new Response(200);
        $body = Stream::create(json_encode([
            'access_token' => 'access-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'refresh-token-123',
        ]));
        $expectedResponse = $expectedResponse->withBody($body);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->with($request, $this->isInstanceOf(Response::class))
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('access-token-123', $responseBody['access_token']);
        $this->assertEquals('Bearer', $responseBody['token_type']);
    }

    public function testHandleRefreshTokenRequest(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'grant_type' => 'refresh_token',
                'refresh_token' => 'refresh-token-123',
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
            ]);

        $expectedResponse = new Response(200);
        $body = Stream::create(json_encode([
            'access_token' => 'new-access-token-456',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'new-refresh-token-456',
        ]));
        $expectedResponse = $expectedResponse->withBody($body);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleWithBasicAuthCredentials(): void
    {
        $credentials = base64_encode('test-client:test-secret');
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Authorization', 'Basic ' . $credentials)
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'auth-code-123',
                'redirect_uri' => 'https://example.com/callback',
            ]);

        $expectedResponse = new Response(200);
        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleWithInvalidMethod(): void
    {
        $request = new ServerRequest('GET', '/token');

        $response = $this->handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('invalid_request', $responseBody['error']);
    }

    public function testHandleWithInvalidContentType(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/json')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'auth-code-123',
            ]);

        $response = $this->handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('invalid_request', $responseBody['error']);
    }

    public function testHandleWithOAuthServerException(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'invalid-code',
            ]);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willThrowException(OAuthServerException::invalidGrant('Invalid authorization code'));

        $response = $this->handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('invalid_grant', $responseBody['error']);
    }

    public function testHandleWithGenericException(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'auth-code-123',
            ]);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willThrowException(new \Exception('Database connection failed'));

        $response = $this->handler->handle($request);

        $this->assertEquals(500, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('server_error', $responseBody['error']);
    }

    public function testHandleWithPKCERequest(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'auth-code-123',
                'client_id' => 'mobile-app',
                'redirect_uri' => 'myapp://callback',
                'code_verifier' => 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
            ]);

        $expectedResponse = new Response(200);
        $body = Stream::create(json_encode([
            'access_token' => 'pkce-access-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]));
        $expectedResponse = $expectedResponse->withBody($body);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('pkce-access-token-123', $responseBody['access_token']);
    }

    public function testHandleWithMagicLinkGrant(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody([
                'grant_type' => 'magic_link',
                'magic_link_token' => 'magic-token-123',
                'client_id' => 'web-app',
            ]);

        $expectedResponse = new Response(200);
        $body = Stream::create(json_encode([
            'access_token' => 'magic-access-token-123',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => 'magic-refresh-token-123',
        ]));
        $expectedResponse = $expectedResponse->withBody($body);

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $responseBody = json_decode((string) $response->getBody(), true);
        $this->assertEquals('magic-access-token-123', $responseBody['access_token']);
    }

    public function testHandleWithEmptyBody(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willThrowException(OAuthServerException::invalidRequest('grant_type'));

        $response = $this->handler->handle($request);

        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testHandleWithMalformedBasicAuth(): void
    {
        $request = new ServerRequest('POST', '/token');
        $request = $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Authorization', 'Basic invalid-base64')
            ->withParsedBody([
                'grant_type' => 'authorization_code',
                'code' => 'auth-code-123',
            ]);

        $expectedResponse = new Response(200);
        $this->authorizationServer->expects($this->once())
            ->method('respondToAccessTokenRequest')
            ->willReturn($expectedResponse);

        $response = $this->handler->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }
}
