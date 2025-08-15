# Event-Driven Architecture

The GraphQL Auth Component uses PSR-14 compliant event-driven architecture to provide extensible and decoupled functionality. This document explains how to work with events and integrate with different frameworks.

## Overview

The component dispatches events at key points in the authentication flow, allowing you to:
- Send verification emails when users register
- Add custom logic after user registration
- Integrate with external services
- Implement audit logging
- Add custom validation or processing

## Events

### UserRegisteredEvent

Dispatched when a user has been successfully registered and committed to the database.

**Properties:**
- `getUserId()`: The ID of the newly registered user
- `getRegistrationContext()`: The registration context containing user data
- `getClientId()`: The OAuth2 client ID
- `getEmail()`: The user's email address
- `getAdditionalData()`: Additional user data from registration
- `getOccurredAt()`: When the event occurred

**Example:**
```php
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;

$event = new UserRegisteredEvent(
    userId: '123',
    registrationContext: $context,
    clientId: 'my-app'
);
```

## Built-in Event Handlers

### SendVerificationEmailHandler

Automatically sends verification emails when users register. This handler:
- Creates a magic link token for email verification
- Sends the verification email via `SendVerificationLinkInterface`
- Logs success/failure events
- Gracefully handles errors without breaking registration

## Framework Integration

### Using the Simple Event Dispatcher (Default)

The component includes a basic PSR-14 compliant event dispatcher for simple use cases:

```php
use Zestic\GraphQL\AuthComponent\Event\SimpleEventDispatcher;
use Zestic\GraphQL\AuthComponent\Event\SimpleListenerProvider;

$listenerProvider = new SimpleListenerProvider();
$eventDispatcher = new SimpleEventDispatcher($listenerProvider);

// Register handlers
$listenerProvider->addListener(
    UserRegisteredEvent::class,
    $container->get(SendVerificationEmailHandler::class)
);
```

### Symfony Integration

Replace the simple event dispatcher with Symfony's:

```php
// config/services.yaml
services:
    Psr\EventDispatcher\EventDispatcherInterface:
        alias: Symfony\Component\EventDispatcher\EventDispatcher

    # Register event handlers
    Zestic\GraphQL\AuthComponent\Event\Handler\SendVerificationEmailHandler:
        tags:
            - { name: kernel.event_listener, event: Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent }
```

### Laravel Integration

Use Laravel's event system:

```php
// In a service provider
use Illuminate\Support\Facades\Event;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;

Event::listen(UserRegisteredEvent::class, SendVerificationEmailHandler::class);

// Or bind the PSR-14 dispatcher
$this->app->bind(EventDispatcherInterface::class, function ($app) {
    return new LaravelEventDispatcher($app['events']);
});
```

### Laminas/Mezzio Integration

Configure in your container:

```php
// config/dependencies.php
use Laminas\EventManager\EventManager;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;

return [
    'factories' => [
        EventDispatcherInterface::class => function ($container) {
            $eventManager = new EventManager();
            
            // Attach listeners
            $eventManager->attach(
                UserRegisteredEvent::class,
                $container->get(SendVerificationEmailHandler::class)
            );
            
            return new LaminasEventDispatcher($eventManager);
        },
    ],
];
```

## Creating Custom Event Handlers

### Simple Handler

```php
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;

class CustomUserRegisteredHandler
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        // Your custom logic here
        $userId = $event->getUserId();
        $email = $event->getEmail();
        
        // Example: Send welcome email, create user profile, etc.
    }
}
```

### Handler with Dependencies

```php
use Psr\Log\LoggerInterface;
use Zestic\GraphQL\AuthComponent\Event\UserRegisteredEvent;

class AuditLogHandler
{
    public function __construct(
        private LoggerInterface $logger,
        private AuditService $auditService
    ) {}

    public function __invoke(UserRegisteredEvent $event): void
    {
        $this->auditService->logUserRegistration([
            'user_id' => $event->getUserId(),
            'email' => $event->getEmail(),
            'client_id' => $event->getClientId(),
            'timestamp' => $event->getOccurredAt(),
        ]);

        $this->logger->info('User registered', [
            'user_id' => $event->getUserId(),
            'email' => $event->getEmail(),
        ]);
    }
}
```

## Configuration

### Registering Multiple Handlers

```php
$listenerProvider->addListener(UserRegisteredEvent::class, $sendEmailHandler);
$listenerProvider->addListener(UserRegisteredEvent::class, $auditLogHandler);
$listenerProvider->addListener(UserRegisteredEvent::class, $welcomeEmailHandler);
```

### Conditional Handlers

```php
class ConditionalHandler
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        $additionalData = $event->getAdditionalData();
        
        if ($additionalData['newsletter_signup'] ?? false) {
            // Subscribe to newsletter
        }
        
        if ($additionalData['account_type'] === 'premium') {
            // Set up premium features
        }
    }
}
```

## Error Handling

Event handlers should handle their own errors gracefully:

```php
class RobustHandler
{
    public function __invoke(UserRegisteredEvent $event): void
    {
        try {
            // Your logic here
        } catch (\Throwable $e) {
            // Log error but don't rethrow
            $this->logger->error('Handler failed', [
                'error' => $e->getMessage(),
                'user_id' => $event->getUserId(),
            ]);
        }
    }
}
```

## Migration from Direct Email Sending

If you were previously using the old RegisterUser with direct email sending:

**Before:**
```php
$registerUser = new RegisterUser(
    $clientRepository,
    $magicLinkTokenFactory,      // ← Remove
    $sendVerificationLink,       // ← Remove  
    $userCreatedHook,
    $userRepository
);
```

**After:**
```php
$registerUser = new RegisterUser(
    $clientRepository,
    $eventDispatcher,            // ← Add
    $userCreatedHook,
    $userRepository
);
```

The email sending is now handled by the `SendVerificationEmailHandler` event handler, which is automatically registered in the default configuration.
