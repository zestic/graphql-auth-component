# OAuth2 Endpoints Setup (v0.3.0)

This document explains how to set up the OAuth2 authorization and token endpoints in your application.

## Overview

The GraphQL Auth Component provides two main OAuth2 handlers:

1. **AuthorizationRequestHandler** - Handles `/authorize` endpoint for authorization requests
2. **TokenRequestHandler** - Handles `/token` endpoint for token exchange

## Laminas Framework Setup

### 1. Route Configuration

Add the following routes to your Laminas application configuration (usually in `config/routes.php` or similar):

```php
<?php

declare(strict_types=1);

use Laminas\Router\Http\Literal;
use Laminas\Router\Http\Method;
use Zestic\GraphQL\AuthComponent\Application\Handler\AuthorizationRequestHandler;
use Zestic\GraphQL\AuthComponent\Application\Handler\MagicLinkVerificationHandler;
use Zestic\GraphQL\AuthComponent\Application\Handler\TokenRequestHandler;

return [
    'router' => [
        'routes' => [
            'oauth2-authorize' => [
                'type' => Method::class,
                'options' => [
                    'verb' => 'get,post',
                    'route' => '/oauth/authorize',
                    'defaults' => [
                        'handler' => AuthorizationRequestHandler::class,
                    ],
                ],
            ],
            'oauth2-token' => [
                'type' => Method::class,
                'options' => [
                    'verb' => 'post',
                    'route' => '/oauth/token',
                    'defaults' => [
                        'handler' => TokenRequestHandler::class,
                    ],
                ],
            ],
            'magic-link-verify' => [
                'type' => Literal::class,
                'options' => [
                    'route' => '/magic-link/verify',
                    'defaults' => [
                        'handler' => MagicLinkVerificationHandler::class,
                    ],
                ],
            ],
        ],
    ],
];
```

### 2. Dependency Injection

The handlers are automatically configured through the `ConfigProvider`. Make sure you have the GraphQL Auth Component registered in your application:

```php
// config/config.php
use Zestic\GraphQL\AuthComponent\Application\ConfigProvider as AuthConfigProvider;

$aggregator = new ConfigAggregator([
    AuthConfigProvider::class,
    // ... other config providers
]);
```

### 3. Middleware Setup (Optional)

You may want to add authentication middleware to the authorization endpoint:

```php
'oauth2-authorize' => [
    'type' => Method::class,
    'options' => [
        'verb' => 'get,post',
        'route' => '/oauth/authorize',
        'defaults' => [
            'handler' => AuthorizationRequestHandler::class,
            'middleware' => [
                AuthenticationMiddleware::class, // Your auth middleware
            ],
        ],
    ],
],
```

## FastRoute Setup

For applications using [FastRoute](https://github.com/nikic/FastRoute), you can set up the OAuth2 endpoints as follows:

### 1. Route Definition

```php
<?php

declare(strict_types=1);

use FastRoute\RouteCollector;
use Zestic\GraphQL\AuthComponent\Application\Handler\AuthorizationRequestHandler;
use Zestic\GraphQL\AuthComponent\Application\Handler\MagicLinkVerificationHandler;
use Zestic\GraphQL\AuthComponent\Application\Handler\TokenRequestHandler;

return function (RouteCollector $r) {
    // OAuth2 Authorization endpoint
    $r->addRoute(['GET', 'POST'], '/oauth/authorize', AuthorizationRequestHandler::class);

    // OAuth2 Token endpoint
    $r->addRoute('POST', '/oauth/token', TokenRequestHandler::class);

    // Magic Link Verification endpoint
    $r->addRoute('GET', '/magic-link/verify', MagicLinkVerificationHandler::class);
};
```

### 2. Dispatcher Setup

```php
<?php

declare(strict_types=1);

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

// Create dispatcher
$dispatcher = \FastRoute\simpleDispatcher(function(RouteCollector $r) {
    // Load routes from file
    $routes = require __DIR__ . '/routes.php';
    $routes($r);
});

// Handle request
function handleRequest(ServerRequestInterface $request, $container): ResponseInterface
{
    $httpMethod = $request->getMethod();
    $uri = $request->getUri()->getPath();

    // Strip query string (?foo=bar) and decode URI
    if (false !== $pos = strpos($uri, '?')) {
        $uri = substr($uri, 0, $pos);
    }
    $uri = rawurldecode($uri);

    $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

    switch ($routeInfo[0]) {
        case Dispatcher::NOT_FOUND:
            return new \Nyholm\Psr7\Response(404, [], 'Not Found');

        case Dispatcher::METHOD_NOT_ALLOWED:
            $allowedMethods = $routeInfo[1];
            return new \Nyholm\Psr7\Response(405, ['Allow' => implode(', ', $allowedMethods)], 'Method Not Allowed');

        case Dispatcher::FOUND:
            $handler = $routeInfo[1];
            $vars = $routeInfo[2];

            // Get handler from container
            $handlerInstance = $container->get($handler);

            // Add route variables to request attributes
            foreach ($vars as $key => $value) {
                $request = $request->withAttribute($key, $value);
            }

            // Handle the request
            return $handlerInstance->handle($request);
    }
}
```

### 3. Integration with PSR-11 Container

```php
<?php

declare(strict_types=1);

use DI\Container;
use DI\ContainerBuilder;
use Zestic\GraphQL\AuthComponent\Application\ConfigProvider;

// Build container
$containerBuilder = new ContainerBuilder();

// Add auth component configuration
$authConfig = (new ConfigProvider())();
$containerBuilder->addDefinitions($authConfig['dependencies']);

// Add your application dependencies
$containerBuilder->addDefinitions([
    // Your application services
]);

$container = $containerBuilder->build();

// Handle the request
$request = \Nyholm\Psr7Server\ServerRequestCreator::fromGlobals();
$response = handleRequest($request, $container);

// Emit response
(new \Laminas\HttpHandlerRunner\Emitter\SapiEmitter())->emit($response);
```

### 4. Advanced Route Configuration with Groups

```php
<?php

declare(strict_types=1);

use FastRoute\RouteCollector;

return function (RouteCollector $r) {
    // OAuth2 endpoints group
    $r->addGroup('/oauth', function (RouteCollector $r) {
        $r->addRoute(['GET', 'POST'], '/authorize', AuthorizationRequestHandler::class);
        $r->addRoute('POST', '/token', TokenRequestHandler::class);
    });

    // Magic link endpoints group
    $r->addGroup('/magic-link', function (RouteCollector $r) {
        $r->addRoute('GET', '/verify', MagicLinkVerificationHandler::class);
    });

    // API endpoints group (if needed)
    $r->addGroup('/api/v1', function (RouteCollector $r) {
        // Your API routes here
    });
};
```

### 5. Middleware Integration

```php
<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Add authentication logic here
        // For example, validate API keys, JWT tokens, etc.

        return $handler->handle($request);
    }
}

// Apply middleware to specific routes
return function (RouteCollector $r) {
    // Public OAuth2 endpoints (no auth required)
    $r->addRoute(['GET', 'POST'], '/oauth/authorize', AuthorizationRequestHandler::class);
    $r->addRoute('POST', '/oauth/token', TokenRequestHandler::class);
    $r->addRoute('GET', '/magic-link/verify', MagicLinkVerificationHandler::class);

    // Protected API endpoints (with auth middleware)
    $r->addGroup('/api', function (RouteCollector $r) {
        // These would need middleware wrapper in your dispatcher
        $r->addRoute('GET', '/user/profile', UserProfileHandler::class);
    });
};
```

### 6. Error Handling

```php
<?php

declare(strict_types=1);

function handleRequest(ServerRequestInterface $request, $container): ResponseInterface
{
    try {
        // ... dispatcher logic from above ...

    } catch (\Throwable $e) {
        // Log the error
        error_log('Request handling error: ' . $e->getMessage());

        // Return appropriate error response
        if ($e instanceof \InvalidArgumentException) {
            return new \Nyholm\Psr7\Response(400, [], 'Bad Request: ' . $e->getMessage());
        }

        // Generic server error
        return new \Nyholm\Psr7\Response(500, [], 'Internal Server Error');
    }
}
```

## Endpoint Usage

### Authorization Endpoint (`/oauth/authorize`)

**Purpose**: Initiates the OAuth2 authorization flow

**Methods**: `GET`, `POST`

**Parameters**:
- `response_type` (required) - Must be "code" for authorization code flow
- `client_id` (required) - The client identifier
- `redirect_uri` (optional) - Where to redirect after authorization
- `scope` (optional) - Requested scopes
- `state` (recommended) - CSRF protection token
- `code_challenge` (required for public clients) - PKCE code challenge
- `code_challenge_method` (optional) - PKCE method ("S256" recommended)

**Example Request**:
```
GET /oauth/authorize?response_type=code&client_id=mobile-app&redirect_uri=myapp://callback&scope=read&state=xyz&code_challenge=E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM&code_challenge_method=S256
```

**Response**: Redirects to `redirect_uri` with authorization code or error

### Token Endpoint (`/oauth/token`)

**Purpose**: Exchanges authorization codes for access tokens

**Method**: `POST`

**Content-Type**: `application/x-www-form-urlencoded`

**Parameters for Authorization Code Grant**:
- `grant_type` (required) - Must be "authorization_code"
- `code` (required) - The authorization code from `/authorize`
- `redirect_uri` (required if used in authorization request)
- `client_id` (required)
- `client_secret` (required for confidential clients)
- `code_verifier` (required if PKCE was used)

**Example Request**:
```bash
curl -X POST /oauth/token \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "grant_type=authorization_code&code=AUTH_CODE&client_id=mobile-app&redirect_uri=myapp://callback&code_verifier=dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk"
```

**Response**:
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_in": 3600,
  "refresh_token": "def50200...",
  "scope": "read write"
}
```

## Authentication Requirements

### Authorization Endpoint

The `AuthorizationRequestHandler` requires the user to be authenticated. It looks for the user ID in:

1. Request attributes (`user_id`)
2. Parsed body (`user_id`)
3. Query parameters (`user_id`)
4. Authorization header (`Bearer token`)

Make sure your authentication middleware sets one of these values.

### Token Endpoint

The `TokenRequestHandler` validates client credentials automatically through the OAuth2 library.

## Error Handling

Both handlers return proper OAuth2 error responses:

- `400 Bad Request` - Invalid request parameters
- `401 Unauthorized` - Invalid client credentials or user not authenticated
- `500 Internal Server Error` - Server errors

Error responses follow the OAuth2 specification format:

```json
{
  "error": "invalid_request",
  "error_description": "The request is missing a required parameter",
  "error_uri": "https://tools.ietf.org/html/rfc6749#section-4.1.2.1"
}
```

## Security Considerations

1. **HTTPS Only**: Always use HTTPS in production
2. **PKCE Recommended**:
   - **Required** for public clients (mobile apps, SPAs)
   - **Optional but recommended** for confidential clients (web apps)
3. **State Parameter**: Use the `state` parameter to prevent CSRF attacks
4. **Short-lived Codes**: Authorization codes expire in 10 minutes
5. **Client Validation**: Confidential clients must provide valid secrets
6. **Modern Web Apps**: Consider using `web-pkce` client type instead of confidential for enhanced security

## Testing

You can test the endpoints using the provided test classes as examples:

- `AuthorizationRequestHandlerTest` - Tests authorization flow scenarios
- `TokenRequestHandlerTest` - Tests token exchange scenarios

Both handlers have comprehensive test coverage including error cases and edge scenarios.
