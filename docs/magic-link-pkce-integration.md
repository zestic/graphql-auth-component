# Magic Link + PKCE Integration Guide (v2.0)

This guide explains how to integrate magic links with PKCE (Proof Key for Code Exchange) for secure authentication in React Native and web applications.

## Overview

The magic link flow in v2.0 supports both traditional and PKCE-secured authentication:

- **Traditional Flow**: Email → Magic Link → Web App Login (no PKCE parameters)
- **PKCE Flow**: Email → Magic Link → App → Token Exchange (with PKCE parameters)

## React Native Setup

### 1. Install Dependencies

```bash
npm install react-native-pkce-challenge
# For Expo
expo install expo-linking expo-secure-store
```

### 2. Configure Deep Linking

**For Expo:**
```json
// app.json
{
  "expo": {
    "scheme": "myapp",
    "linking": {
      "prefixes": ["myapp://"]
    }
  }
}
```

**For bare React Native:**
```xml
<!-- android/app/src/main/AndroidManifest.xml -->
<activity android:name=".MainActivity">
  <intent-filter android:autoVerify="true">
    <action android:name="android.intent.action.VIEW" />
    <category android:name="android.intent.category.DEFAULT" />
    <category android:name="android.intent.category.BROWSABLE" />
    <data android:scheme="myapp" />
  </intent-filter>
</activity>
```

### 3. Register OAuth2 Client

```bash
php scripts/register-oauth-client.php \
  --name="My React Native App" \
  --type=public \
  --redirect-uri="myapp://auth/callback"
```

This will output your `client_id` (e.g., `my-react-native-app-a1b2c3d4`).

## Implementation

### 1. Magic Link Request with PKCE

**Note**: For traditional magic links (no PKCE), simply omit the PKCE parameters (`clientId`, `codeChallenge`, etc.) from the input.

```javascript
import { generateCodeChallenge, generateCodeVerifier } from 'react-native-pkce-challenge';
import * as SecureStore from 'expo-secure-store';

const sendMagicLinkWithPKCE = async (email) => {
  try {
    // Generate PKCE parameters
    const codeVerifier = generateCodeVerifier();
    const codeChallenge = await generateCodeChallenge(codeVerifier);
    const state = generateRandomString(32); // For CSRF protection
    
    // Store securely for later use
    await SecureStore.setItemAsync('pkce_code_verifier', codeVerifier);
    await SecureStore.setItemAsync('pkce_state', state);
    
    // Enhanced GraphQL mutation
    const response = await client.mutate({
      mutation: gql`
        mutation SendMagicLink($input: SendMagicLinkInput!) {
          sendMagicLink(input: $input) {
            success
            message
            code
          }
        }
      `,
      variables: {
        input: {
          email,
          clientId: 'my-react-native-app-a1b2c3d4', // Your registered client ID
          codeChallenge,
          codeChallengeMethod: 'S256',
          redirectUri: 'myapp://auth/callback',
          scope: 'read write',
          state,
        }
      }
    });
    
    if (response.data.sendMagicLink.success) {
      // Show success message - user should check email
      Alert.alert('Check Your Email', 'We sent you a magic link!');
    }
    
  } catch (error) {
    console.error('Magic link error:', error);
  }
};

function generateRandomString(length) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let result = '';
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}
```

### 2. Handle Deep Link Response

```javascript
import * as Linking from 'expo-linking';
import { useEffect } from 'react';

const AuthScreen = () => {
  useEffect(() => {
    // Handle initial URL if app was closed
    Linking.getInitialURL().then(handleDeepLink);
    
    // Handle URL if app was backgrounded
    const subscription = Linking.addEventListener('url', ({ url }) => {
      handleDeepLink(url);
    });
    
    return () => subscription?.remove();
  }, []);
  
  const handleDeepLink = async (url) => {
    if (!url || !url.includes('auth/callback')) return;
    
    try {
      const { queryParams } = Linking.parse(url);
      const { magic_link_token, state } = queryParams;
      
      if (!magic_link_token) {
        Alert.alert('Error', 'Invalid magic link');
        return;
      }
      
      // Verify state parameter (CSRF protection)
      const storedState = await SecureStore.getItemAsync('pkce_state');
      if (state !== storedState) {
        Alert.alert('Error', 'Invalid state parameter');
        return;
      }
      
      // Exchange magic link token for access tokens
      await exchangeTokens(magic_link_token);
      
    } catch (error) {
      console.error('Deep link error:', error);
      Alert.alert('Error', 'Failed to process magic link');
    }
  };
  
  // ... rest of component
};
```

### 3. Token Exchange

```javascript
const exchangeTokens = async (magicLinkToken) => {
  try {
    const codeVerifier = await SecureStore.getItemAsync('pkce_code_verifier');
    
    if (!codeVerifier) {
      throw new Error('PKCE code verifier not found');
    }
    
    const response = await fetch('https://yourapi.com/oauth/token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        grant_type: 'magic_link',
        token: magicLinkToken, // Note: parameter name is 'token' not 'magic_link_token'
        client_id: 'my-react-native-app-a1b2c3d4',
        code_verifier: codeVerifier,
      }),
    });
    
    const tokens = await response.json();
    
    if (response.ok) {
      // Store tokens securely
      await SecureStore.setItemAsync('access_token', tokens.access_token);
      await SecureStore.setItemAsync('refresh_token', tokens.refresh_token);
      
      // Clean up PKCE data
      await SecureStore.deleteItemAsync('pkce_code_verifier');
      await SecureStore.deleteItemAsync('pkce_state');
      
      // Navigate to authenticated area
      navigation.navigate('Dashboard');
      
    } else {
      throw new Error(tokens.error_description || 'Token exchange failed');
    }
    
  } catch (error) {
    console.error('Token exchange error:', error);
    Alert.alert('Error', 'Authentication failed');
  }
};
```

## Backend Configuration

### 1. Update GraphQL Resolver

```php
// In your GraphQL resolver
public function sendMagicLink($root, array $args, $context): array
{
    $input = $args['input'];

    // Create context from input (supports both traditional and PKCE flows)
    $magicLinkContext = MagicLinkContext::fromGraphQLInput($input);

    // Use the unified interactor method
    return $this->sendMagicLinkInteractor->send($magicLinkContext);
}
```

### 2. Configure Magic Link URLs

In your `SendMagicLinkInterface` implementation, generate URLs like:

```php
// For PKCE-enabled magic links
$magicLinkUrl = "https://yourapi.com/magic-link/verify?token=" . $token->token;

// For regular magic links  
$magicLinkUrl = "https://yourapp.com/auth/magic-link?token=" . $token->token;
```

### 3. Add Route for Magic Link Verification

```php
// In your Laminas routes config
'magic-link-verify' => [
    'type' => Literal::class,
    'options' => [
        'route' => '/magic-link/verify',
        'defaults' => [
            'handler' => MagicLinkVerificationHandler::class,
        ],
    ],
],
```

## Security Considerations

1. **PKCE Required**: Public clients (mobile apps) must use PKCE
2. **State Parameter**: Always use state parameter for CSRF protection
3. **Secure Storage**: Store code_verifier and tokens in secure storage
4. **HTTPS Only**: All endpoints must use HTTPS in production
5. **Token Expiration**: Magic link tokens expire in 10 minutes by default
6. **Deep Link Validation**: Always validate deep link parameters

## Testing

Test the flow with different scenarios:

1. **Happy Path**: Email → Magic Link → App → Tokens
2. **Expired Token**: Test with expired magic link
3. **Invalid State**: Test CSRF protection
4. **Missing PKCE**: Test error handling
5. **Network Errors**: Test offline scenarios

## Troubleshooting

**Common Issues:**

1. **Deep links not working**: Check URL scheme configuration
2. **PKCE validation fails**: Verify code_verifier storage/retrieval
3. **State mismatch**: Ensure state parameter is stored and validated
4. **Token exchange fails**: Check client_id and endpoint URLs

**Debug Tips:**

1. Log all PKCE parameters during generation
2. Verify magic link URLs in email templates
3. Test deep linking with `npx uri-scheme open myapp://auth/callback --ios`
4. Check network requests in React Native debugger
