# Magic Link Configuration

The `MagicLinkConfig` class provides flexible configuration for magic link URLs and messages. This allows you to customize redirect paths and success messages without hardcoding values in your handlers.

## Configuration Structure

Add the magic link configuration to your application's config file:

```php
// config/autoload/auth.global.php
return [
    'auth' => [
        'magicLink' => [
            'webAppUrl' => 'https://yourapp.com',
            'authCallbackPath' => '/auth/callback',
            'magicLinkPath' => '/auth/magic-link',
            'defaultSuccessMessage' => 'Authentication successful',
            'registrationSuccessMessage' => 'Registration verified successfully',
        ],
    ],
];
```

## Configuration Options

| Key | Type | Description | Default |
|-----|------|-------------|---------|
| `webAppUrl` | `string` | Base URL of your web application | `https://yourapp.com` |
| `authCallbackPath` | `string` | Path for authentication callbacks | `/auth/callback` |
| `magicLinkPath` | `string` | Path for traditional magic link handling | `/auth/magic-link` |
| `defaultSuccessMessage` | `string` | Default success message for authentication | `Authentication successful` |
| `registrationSuccessMessage` | `string` | Success message for registration verification | `Registration verified successfully` |

## Environment-Specific Configuration

### Development
```php
// config/autoload/auth.local.php
return [
    'auth' => [
        'magicLink' => [
            'webAppUrl' => 'http://localhost:3000',
        ],
    ],
];
```

### Production
```php
// config/autoload/auth.global.php
return [
    'auth' => [
        'magicLink' => [
            'webAppUrl' => 'https://myapp.com',
            'authCallbackPath' => '/auth/callback',
            'magicLinkPath' => '/auth/magic-link',
        ],
    ],
];
```

## URL Generation Examples

The `MagicLinkConfig` provides several methods for generating URLs:

### Basic URL Generation
```php
$config = $container->get(MagicLinkConfig::class);

// Get base URLs
$callbackUrl = $config->getAuthCallbackUrl();
// Result: https://yourapp.com/auth/callback

$magicLinkUrl = $config->getMagicLinkUrl();
// Result: https://yourapp.com/auth/magic-link
```

### URL Generation with Parameters
```php
// Auth callback with parameters
$callbackUrl = $config->createAuthCallbackUrl([
    'magic_link_token' => 'abc123',
    'message' => 'Registration verified successfully'
]);
// Result: https://yourapp.com/auth/callback?magic_link_token=abc123&message=Registration+verified+successfully

// Magic link with token
$magicLinkUrl = $config->createMagicLinkUrl(['token' => 'def456']);
// Result: https://yourapp.com/auth/magic-link?token=def456

// PKCE redirect (mobile app)
$pkceUrl = $config->createPkceRedirectUrl('myapp://auth/callback', [
    'magic_link_token' => 'ghi789',
    'state' => 'xyz123'
]);
// Result: myapp://auth/callback?magic_link_token=ghi789&state=xyz123
```

## Flow-Specific Redirects

### Registration Verification Flow
```
1. User clicks email link: https://yourapp.com/magic-link/verify?token=reg-token
2. Handler validates registration token
3. Redirects to: https://yourapp.com/auth/callback?magic_link_token=reg-token&message=Registration+verified+successfully
```

### Login Magic Link Flow (Traditional)
```
1. User clicks email link: https://yourapp.com/magic-link/verify?token=login-token
2. Handler validates login token (no PKCE data)
3. Redirects to: https://yourapp.com/auth/magic-link?token=login-token
```

### Login Magic Link Flow (PKCE)
```
1. User clicks email link: https://yourapp.com/magic-link/verify?token=pkce-token
2. Handler validates login token (with PKCE data)
3. Redirects to: myapp://auth/callback?magic_link_token=pkce-token&state=abc123
```

## Custom Configuration Example

For a multi-tenant application with custom paths:

```php
return [
    'auth' => [
        'magicLink' => [
            'webAppUrl' => 'https://tenant1.myapp.com',
            'authCallbackPath' => '/dashboard/auth/complete',
            'magicLinkPath' => '/dashboard/auth/magic-link',
            'defaultSuccessMessage' => 'Welcome to your dashboard!',
            'registrationSuccessMessage' => 'Account verified! Welcome aboard!',
        ],
    ],
];
```

## Integration with Frontend Applications

### React/Next.js Example
```javascript
// Handle auth callback in your React app
// Route: /auth/callback

import { useRouter } from 'next/router';
import { useEffect } from 'react';

export default function AuthCallback() {
  const router = useRouter();
  const { magic_link_token, message, state } = router.query;

  useEffect(() => {
    if (magic_link_token) {
      // Exchange magic link token for access token
      exchangeTokenForAccessToken(magic_link_token)
        .then(accessToken => {
          // Store token and redirect to dashboard
          localStorage.setItem('access_token', accessToken);
          router.push('/dashboard');
        })
        .catch(error => {
          console.error('Authentication failed:', error);
          router.push('/login?error=auth_failed');
        });
    }
  }, [magic_link_token]);

  return <div>Completing authentication...</div>;
}
```

### React Native Example
```javascript
// Handle deep link in React Native
// Deep link: myapp://auth/callback

import { Linking } from 'react-native';
import { useEffect } from 'react';

export default function App() {
  useEffect(() => {
    const handleDeepLink = (url) => {
      if (url.includes('/auth/callback')) {
        const urlParams = new URLSearchParams(url.split('?')[1]);
        const magicLinkToken = urlParams.get('magic_link_token');
        const state = urlParams.get('state');
        
        if (magicLinkToken) {
          // Exchange token and navigate to main app
          exchangeTokenForAccessToken(magicLinkToken)
            .then(accessToken => {
              // Store token and navigate
              AsyncStorage.setItem('access_token', accessToken);
              navigation.navigate('Home');
            });
        }
      }
    };

    Linking.addEventListener('url', handleDeepLink);
    return () => Linking.removeEventListener('url', handleDeepLink);
  }, []);

  // ... rest of your app
}
```

## Benefits

✅ **Flexible Configuration**: Customize URLs and messages per environment  
✅ **No Hardcoded Values**: All URLs generated from configuration  
✅ **Environment-Specific**: Different settings for dev/staging/production  
✅ **Multi-Tenant Support**: Different configurations per tenant  
✅ **Type Safety**: Strongly typed configuration with clear interfaces  
✅ **Testable**: Easy to mock and test with different configurations  

The `MagicLinkConfig` provides a clean, maintainable way to manage all magic link-related URLs and messages in your application!
