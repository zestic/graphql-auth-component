# Expo Deep Link Setup for Magic Link Authentication

This guide provides step-by-step instructions for setting up deep links in Expo applications to handle magic link authentication callbacks.

## Prerequisites

- Expo CLI installed (`npm install -g @expo/cli`)
- Expo project created (`npx create-expo-app MyApp`)
- React Navigation installed for routing

## 1. Configure Deep Linking in Expo

### Update app.json/app.config.js

```json
{
  "expo": {
    "name": "My App",
    "slug": "my-app",
    "scheme": "myapp",
    "version": "1.0.0",
    "platforms": ["ios", "android", "web"],
    "web": {
      "bundler": "metro"
    },
    "linking": {
      "prefixes": [
        "myapp://",
        "https://myapp.com"
      ]
    },
    "ios": {
      "bundleIdentifier": "com.yourcompany.myapp",
      "associatedDomains": ["applinks:myapp.com"]
    },
    "android": {
      "package": "com.yourcompany.myapp",
      "intentFilters": [
        {
          "action": "VIEW",
          "autoVerify": true,
          "data": [
            {
              "scheme": "https",
              "host": "myapp.com"
            }
          ],
          "category": ["BROWSABLE", "DEFAULT"]
        }
      ]
    }
  }
}
```

## 2. Install Required Dependencies

```bash
# Core navigation
npm install @react-navigation/native @react-navigation/native-stack

# Expo dependencies
npx expo install expo-linking expo-secure-store react-native-screens react-native-safe-area-context

# PKCE support
npm install react-native-pkce-challenge
```

## 3. Set Up Navigation with Deep Links

### Create Navigation Structure

```javascript
// App.js
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import * as Linking from 'expo-linking';

import LoginScreen from './screens/LoginScreen';
import AuthCallbackScreen from './screens/AuthCallbackScreen';
import HomeScreen from './screens/HomeScreen';

const Stack = createNativeStackNavigator();

const linking = {
  prefixes: ['myapp://', 'https://myapp.com'],
  config: {
    screens: {
      Login: 'login',
      AuthCallback: 'auth/callback',
      Home: 'home',
    },
  },
};

export default function App() {
  return (
    <NavigationContainer linking={linking}>
      <Stack.Navigator initialRouteName="Login">
        <Stack.Screen 
          name="Login" 
          component={LoginScreen}
          options={{ title: 'Sign In' }}
        />
        <Stack.Screen 
          name="AuthCallback" 
          component={AuthCallbackScreen}
          options={{ 
            title: 'Completing Sign In...',
            headerShown: false 
          }}
        />
        <Stack.Screen 
          name="Home" 
          component={HomeScreen}
          options={{ title: 'Dashboard' }}
        />
      </Stack.Navigator>
    </NavigationContainer>
  );
}
```

## 4. Create Auth Callback Screen

```javascript
// screens/AuthCallbackScreen.js
import React, { useEffect, useState } from 'react';
import { View, Text, ActivityIndicator, Alert, StyleSheet } from 'react-native';
import { useNavigation, useRoute } from '@react-navigation/native';
import * as SecureStore from 'expo-secure-store';

export default function AuthCallbackScreen() {
  const navigation = useNavigation();
  const route = useRoute();
  const [status, setStatus] = useState('Processing...');

  useEffect(() => {
    handleAuthCallback();
  }, []);

  const handleAuthCallback = async () => {
    try {
      const { magic_link_token, state, message } = route.params || {};

      if (!magic_link_token) {
        throw new Error('No magic link token received');
      }

      // Validate state parameter (CSRF protection)
      const storedState = await SecureStore.getItemAsync('auth_state');
      if (state && storedState && state !== storedState) {
        throw new Error('Invalid state parameter');
      }

      setStatus('Exchanging token...');

      // Exchange magic link token for access token
      const accessToken = await exchangeTokenForAccessToken(magic_link_token);

      // Store access token securely
      await SecureStore.setItemAsync('access_token', accessToken);

      // Clean up stored PKCE data
      await SecureStore.deleteItemAsync('code_verifier');
      await SecureStore.deleteItemAsync('auth_state');

      setStatus('Success! Redirecting...');

      // Show success message if provided
      if (message) {
        Alert.alert('Success', decodeURIComponent(message));
      }

      // Navigate to main app
      setTimeout(() => {
        navigation.reset({
          index: 0,
          routes: [{ name: 'Home' }],
        });
      }, 1000);

    } catch (error) {
      console.error('Auth callback error:', error);
      setStatus('Authentication failed');
      
      Alert.alert(
        'Authentication Error',
        error.message || 'Failed to complete authentication',
        [
          {
            text: 'Try Again',
            onPress: () => navigation.navigate('Login'),
          },
        ]
      );
    }
  };

  const exchangeTokenForAccessToken = async (magicLinkToken) => {
    const codeVerifier = await SecureStore.getItemAsync('code_verifier');
    
    const response = await fetch('https://yourapi.com/oauth/token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        grant_type: 'magic_link',
        token: magicLinkToken,
        client_id: 'your-mobile-app-client-id',
        code_verifier: codeVerifier,
      }),
    });

    if (!response.ok) {
      const error = await response.json();
      throw new Error(error.error_description || 'Token exchange failed');
    }

    const data = await response.json();
    return data.access_token;
  };

  return (
    <View style={styles.container}>
      <ActivityIndicator size="large" color="#007bff" />
      <Text style={styles.status}>{status}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#f5f5f5',
  },
  status: {
    marginTop: 20,
    fontSize: 16,
    color: '#666',
    textAlign: 'center',
  },
});
```

## 5. Update Login Screen for Magic Links

```javascript
// screens/LoginScreen.js
import React, { useState } from 'react';
import { View, Text, TextInput, TouchableOpacity, Alert, StyleSheet } from 'react-native';
import { generateCodeChallenge, generateCodeVerifier } from 'react-native-pkce-challenge';
import * as SecureStore from 'expo-secure-store';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);

  const sendMagicLink = async () => {
    if (!email) {
      Alert.alert('Error', 'Please enter your email address');
      return;
    }

    setLoading(true);

    try {
      // Generate PKCE parameters
      const codeVerifier = generateCodeVerifier();
      const codeChallenge = await generateCodeChallenge(codeVerifier);
      const state = Math.random().toString(36).substring(2, 15);

      // Store PKCE data securely
      await SecureStore.setItemAsync('code_verifier', codeVerifier);
      await SecureStore.setItemAsync('auth_state', state);

      // Send magic link request
      const response = await fetch('https://yourapi.com/graphql', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          query: `
            mutation SendMagicLink($input: SendMagicLinkInput!) {
              sendMagicLink(input: $input) {
                success
                message
              }
            }
          `,
          variables: {
            input: {
              email,
              clientId: 'your-mobile-app-client-id',
              codeChallenge,
              codeChallengeMethod: 'S256',
              redirectUri: 'myapp://auth/callback',
              state,
              scope: 'read write',
            },
          },
        }),
      });

      const result = await response.json();

      if (result.data?.sendMagicLink?.success) {
        Alert.alert(
          'Magic Link Sent!',
          'Check your email and tap the magic link to sign in.',
          [{ text: 'OK' }]
        );
      } else {
        throw new Error(result.data?.sendMagicLink?.message || 'Failed to send magic link');
      }

    } catch (error) {
      console.error('Magic link error:', error);
      Alert.alert('Error', error.message || 'Failed to send magic link');
    } finally {
      setLoading(false);
    }
  };

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Sign In</Text>
      
      <TextInput
        style={styles.input}
        placeholder="Enter your email"
        value={email}
        onChangeText={setEmail}
        keyboardType="email-address"
        autoCapitalize="none"
        autoCorrect={false}
      />
      
      <TouchableOpacity 
        style={[styles.button, loading && styles.buttonDisabled]}
        onPress={sendMagicLink}
        disabled={loading}
      >
        <Text style={styles.buttonText}>
          {loading ? 'Sending...' : 'Send Magic Link'}
        </Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    padding: 20,
    backgroundColor: '#fff',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
    textAlign: 'center',
    marginBottom: 30,
  },
  input: {
    borderWidth: 1,
    borderColor: '#ddd',
    padding: 15,
    borderRadius: 8,
    marginBottom: 20,
    fontSize: 16,
  },
  button: {
    backgroundColor: '#007bff',
    padding: 15,
    borderRadius: 8,
    alignItems: 'center',
  },
  buttonDisabled: {
    backgroundColor: '#ccc',
  },
  buttonText: {
    color: '#fff',
    fontSize: 16,
    fontWeight: 'bold',
  },
});
```

## 6. Testing Deep Links

### Test in Development

```bash
# Test iOS Simulator
npx uri-scheme open myapp://auth/callback?magic_link_token=test123&state=abc --ios

# Test Android Emulator
npx uri-scheme open myapp://auth/callback?magic_link_token=test123&state=abc --android

# Test with parameters
npx uri-scheme open "myapp://auth/callback?magic_link_token=test123&state=abc&message=Welcome" --ios
```

### Test in Expo Go

```bash
# Open in Expo Go
npx uri-scheme open exp://192.168.1.100:19000/--/auth/callback?magic_link_token=test123 --ios
```

### Manual Testing Checklist

- [ ] App opens when clicking magic link in email
- [ ] Deep link parameters are correctly parsed
- [ ] State parameter validation works
- [ ] Token exchange completes successfully
- [ ] User is redirected to main app
- [ ] Error handling works for invalid tokens
- [ ] App handles being closed vs backgrounded

## 7. Troubleshooting

### Common Issues

**Deep links not working:**
```bash
# Check if URL scheme is registered
npx uri-scheme list

# Test scheme registration
npx uri-scheme open myapp:// --ios
```

**App doesn't open from email:**
- Verify `scheme` in app.json matches redirect URI
- Check email client (some block custom schemes)
- Test with different email clients
- Ensure app is installed on device

**Parameters not received:**
```javascript
// Debug route parameters
console.log('Route params:', route.params);
console.log('All route data:', JSON.stringify(route, null, 2));
```

**State validation fails:**
```javascript
// Debug state parameter
const storedState = await SecureStore.getItemAsync('auth_state');
console.log('Stored state:', storedState);
console.log('Received state:', state);
```

### Debug Tools

**Enable deep link debugging:**
```javascript
// Add to App.js
import * as Linking from 'expo-linking';

// Debug all incoming URLs
Linking.addEventListener('url', ({ url }) => {
  console.log('Deep link received:', url);
});
```

**Network debugging:**
```javascript
// Add request/response logging
const response = await fetch(url, options);
console.log('Request:', { url, options });
console.log('Response:', response.status, await response.text());
```

## 8. Security Best Practices

✅ **Always validate state parameter** for CSRF protection
✅ **Store sensitive data in SecureStore** not AsyncStorage
✅ **Use HTTPS URLs** for production deep links
✅ **Validate magic link tokens** before processing
✅ **Clean up stored data** after successful authentication
✅ **Handle expired tokens** gracefully
✅ **Log security events** for monitoring

This setup provides a complete, production-ready deep link implementation for Expo apps with magic link authentication! 🚀
