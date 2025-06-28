<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Handler;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Zestic\GraphQL\AuthComponent\Application\Handler\AuthorizationRequestHandler;
use Zestic\GraphQL\AuthComponent\Repository\UserRepositoryInterface;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;
use Zestic\GraphQL\AuthComponent\Entity\User;

class AuthorizationRequestHandlerTest extends TestCase
{
    private AuthorizationRequestHandler $handler;
    private AuthorizationServer $authorizationServer;
    private UserRepositoryInterface $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->authorizationServer = $this->createMock(AuthorizationServer::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        
        $this->handler = new AuthorizationRequestHandler(
            $this->authorizationServer,
            $this->userRepository
        );
    }

    public function testHandleSuccessfulAuthorizationRequest(): void
    {
        $request = new ServerRequest('GET', '/authorize?client_id=test&response_type=code');
        $request = $request->withAttribute('user_id', 'user123');
        
        $user = new User([], 'Test User', 'test@example.com', 'user123');
        
        $client = new ClientEntity();
        $client->setIdentifier('test-client');
        
        $authRequest = $this->createMock(AuthorizationRequest::class);
        $authRequest->expects($this->once())
            ->method('getClient')
            ->willReturn($client);
        $authRequest->expects($this->once())
            ->method('setUser')
            ->with($user);
        $authRequest->expects($this->once())
            ->method('setAuthorizationApproved')
            ->with(true);
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->with($request)
            ->willReturn($authRequest);
        
        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user123')
            ->willReturn($user);
        
        $expectedResponse = new Response(302);
        $this->authorizationServer->expects($this->once())
            ->method('completeAuthorizationRequest')
            ->with($authRequest, $this->isInstanceOf(Response::class))
            ->willReturn($expectedResponse);
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleWithUserIdInParsedBody(): void
    {
        $request = new ServerRequest('POST', '/authorize');
        $request = $request->withParsedBody(['user_id' => 'user456', 'approve' => '1']);
        
        $user = new User([], 'Test User', 'test@example.com', 'user456');
        
        $client = new ClientEntity();
        $client->setIdentifier('test-client');
        
        $authRequest = $this->createMock(AuthorizationRequest::class);
        $authRequest->expects($this->once())
            ->method('getClient')
            ->willReturn($client);
        $authRequest->expects($this->once())
            ->method('setUser')
            ->with($user);
        $authRequest->expects($this->once())
            ->method('setAuthorizationApproved')
            ->with(true);
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($authRequest);
        
        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user456')
            ->willReturn($user);
        
        $expectedResponse = new Response(302);
        $this->authorizationServer->expects($this->once())
            ->method('completeAuthorizationRequest')
            ->willReturn($expectedResponse);
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleWithUserIdInQueryParams(): void
    {
        $request = new ServerRequest('GET', '/authorize?user_id=user789&approve=1');
        
        $user = new User([], 'Test User', 'test@example.com', 'user789');
        
        $client = new ClientEntity();
        $client->setIdentifier('test-client');
        
        $authRequest = $this->createMock(AuthorizationRequest::class);
        $authRequest->expects($this->once())
            ->method('getClient')
            ->willReturn($client);
        $authRequest->expects($this->once())
            ->method('setUser')
            ->with($user);
        $authRequest->expects($this->once())
            ->method('setAuthorizationApproved')
            ->with(true);
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($authRequest);
        
        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user789')
            ->willReturn($user);
        
        $expectedResponse = new Response(302);
        $this->authorizationServer->expects($this->once())
            ->method('completeAuthorizationRequest')
            ->willReturn($expectedResponse);
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleWithAuthorizationHeader(): void
    {
        $request = new ServerRequest('GET', '/authorize');
        $request = $request->withHeader('Authorization', 'Bearer user-token-123');
        
        $user = new User([], 'Test User', 'test@example.com', 'user-token-123');
        
        $client = new ClientEntity();
        $client->setIdentifier('test-client');
        
        $authRequest = $this->createMock(AuthorizationRequest::class);
        $authRequest->expects($this->once())
            ->method('getClient')
            ->willReturn($client);
        $authRequest->expects($this->once())
            ->method('setUser')
            ->with($user);
        $authRequest->expects($this->once())
            ->method('setAuthorizationApproved')
            ->with(true);
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($authRequest);
        
        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user-token-123')
            ->willReturn($user);
        
        $expectedResponse = new Response(302);
        $this->authorizationServer->expects($this->once())
            ->method('completeAuthorizationRequest')
            ->willReturn($expectedResponse);
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleWithNoUserId(): void
    {
        $request = new ServerRequest('GET', '/authorize?client_id=test&response_type=code');
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($this->createMock(AuthorizationRequest::class));
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testHandleWithUserNotFound(): void
    {
        $request = new ServerRequest('GET', '/authorize?client_id=test&response_type=code');
        $request = $request->withAttribute('user_id', 'nonexistent');
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($this->createMock(AuthorizationRequest::class));
        
        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('nonexistent')
            ->willReturn(null);
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function testHandleWithOAuthServerException(): void
    {
        $request = new ServerRequest('GET', '/authorize?invalid=params');
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willThrowException(OAuthServerException::invalidRequest('client_id'));
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(400, $response->getStatusCode());
    }

    public function testHandleWithGenericException(): void
    {
        $request = new ServerRequest('GET', '/authorize');
        $request = $request->withAttribute('user_id', 'user123');
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willThrowException(new \Exception('Database connection failed'));
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testHandleWithExplicitApprovalDenied(): void
    {
        $request = new ServerRequest('POST', '/authorize');
        $request = $request->withParsedBody(['user_id' => 'user123', 'approve' => '0']);
        
        $user = new User([], 'Test User', 'test@example.com', 'user123');
        
        $client = new ClientEntity();
        $client->setIdentifier('test-client');
        
        $authRequest = $this->createMock(AuthorizationRequest::class);
        $authRequest->expects($this->once())
            ->method('getClient')
            ->willReturn($client);
        $authRequest->expects($this->once())
            ->method('setUser')
            ->with($user);
        $authRequest->expects($this->once())
            ->method('setAuthorizationApproved')
            ->with(false);
        
        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($authRequest);
        
        $this->userRepository->expects($this->once())
            ->method('findUserById')
            ->with('user123')
            ->willReturn($user);
        
        $expectedResponse = new Response(302);
        $this->authorizationServer->expects($this->once())
            ->method('completeAuthorizationRequest')
            ->willReturn($expectedResponse);
        
        $response = $this->handler->handle($request);
        
        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleWithEmptyAuthorizationHeader(): void
    {
        $request = new ServerRequest('GET', '/authorize');
        $request = $request->withHeader('Authorization', 'Bearer ');

        $this->authorizationServer->expects($this->once())
            ->method('validateAuthorizationRequest')
            ->willReturn($this->createMock(AuthorizationRequest::class));

        $response = $this->handler->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
    }
}
