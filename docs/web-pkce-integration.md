# Web Application PKCE Integration Guide (v2.0)

This guide explains how to implement PKCE (Proof Key for Code Exchange) in web applications for enhanced security with magic links.

## Why PKCE for Web Applications?

1. **Enhanced Security**: Protects against authorization code interception
2. **No Client Secret Exposure**: Eliminates risk of secrets in frontend code
3. **Future-Proof**: OAuth2 best practices recommend PKCE for all clients
4. **Consistent Flow**: Same authentication pattern across web and mobile

## Client Types Comparison

| Client Type | Secret Required | PKCE Required | Use Case |
|-------------|----------------|---------------|----------|
| `public` | ❌ No | ✅ Yes | Mobile apps, native apps |
| `web-pkce` | ❌ No | ✅ Yes | Modern SPAs, PWAs |
| `confidential` | ✅ Yes | 🔶 Optional | Traditional server-side apps |

## Setup

### 1. Register Web PKCE Client

```bash
php scripts/register-oauth-client.php \
  --name="My Web App" \
  --type=web-pkce \
  --redirect-uri="https://myapp.com/auth/callback"
```

Output:
```
✅ OAuth2 Client registered successfully!

Client Details:
===============
Client ID:     my-web-app-a1b2c3d4
Client Name:   My Web App
Client Type:   web-pkce
Redirect URI:  https://myapp.com/auth/callback

🔒 This is a public client (no secret required). Perfect for web application with PKCE!
🛡️  PKCE is REQUIRED for this client type for security.

🔧 Integration Instructions:
============================
For Modern Web Application with PKCE:
1. Install: npm install pkce-challenge (or similar PKCE library)
2. Use this client_id in your sendMagicLink mutation: 'my-web-app-a1b2c3d4'
3. Generate PKCE challenge/verifier pairs in the browser
4. Store code_verifier securely (sessionStorage/localStorage)
5. No client secret needed - more secure for SPAs!
```

### 2. Install PKCE Library

```bash
npm install pkce-challenge
# or
npm install @peculiar/webcrypto # for crypto.subtle polyfill if needed
```

## Implementation

### 1. PKCE Helper Functions

```javascript
// pkce-utils.js
import { generateChallenge, generateVerifier } from 'pkce-challenge';

export class PKCEManager {
  static generatePKCE() {
    const codeVerifier = generateVerifier();
    const codeChallenge = generateChallenge(codeVerifier);
    
    return {
      codeVerifier,
      codeChallenge,
      codeChallengeMethod: 'S256'
    };
  }
  
  static storePKCE(codeVerifier, state) {
    // Store securely in sessionStorage (cleared on tab close)
    sessionStorage.setItem('pkce_code_verifier', codeVerifier);
    sessionStorage.setItem('pkce_state', state);
  }
  
  static retrievePKCE() {
    return {
      codeVerifier: sessionStorage.getItem('pkce_code_verifier'),
      state: sessionStorage.getItem('pkce_state')
    };
  }
  
  static clearPKCE() {
    sessionStorage.removeItem('pkce_code_verifier');
    sessionStorage.removeItem('pkce_state');
  }
  
  static generateState() {
    return btoa(crypto.getRandomValues(new Uint8Array(32)));
  }
}
```

### 2. Enhanced Magic Link Request

```javascript
// auth.js
import { PKCEManager } from './pkce-utils.js';

export async function sendMagicLinkWithPKCE(email) {
  try {
    // Generate PKCE parameters
    const { codeVerifier, codeChallenge, codeChallengeMethod } = PKCEManager.generatePKCE();
    const state = PKCEManager.generateState();
    
    // Store for later use
    PKCEManager.storePKCE(codeVerifier, state);
    
    // GraphQL mutation
    const response = await fetch('/graphql', {
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
              code
            }
          }
        `,
        variables: {
          input: {
            email,
            clientId: 'my-web-app-a1b2c3d4', // Your registered client ID
            codeChallenge,
            codeChallengeMethod,
            redirectUri: 'https://myapp.com/auth/callback',
            scope: 'read write',
            state,
          }
        }
      })
    });
    
    const result = await response.json();
    
    if (result.data.sendMagicLink.success) {
      // Show success message
      showMessage('Check your email for the magic link!');
      
      // Optionally redirect to a "check email" page
      window.location.href = '/auth/check-email';
    } else {
      throw new Error(result.data.sendMagicLink.message);
    }
    
  } catch (error) {
    console.error('Magic link error:', error);
    showError('Failed to send magic link. Please try again.');
  }
}
```

### 3. Handle Magic Link Callback

```javascript
// auth-callback.js
import { PKCEManager } from './pkce-utils.js';

export async function handleMagicLinkCallback() {
  try {
    // Parse URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const magicLinkToken = urlParams.get('magic_link_token');
    const state = urlParams.get('state');
    
    if (!magicLinkToken) {
      throw new Error('Missing magic link token');
    }
    
    // Retrieve stored PKCE data
    const { codeVerifier, state: storedState } = PKCEManager.retrievePKCE();
    
    if (!codeVerifier) {
      throw new Error('PKCE code verifier not found. Please start the login process again.');
    }
    
    // Verify state parameter (CSRF protection)
    if (state !== storedState) {
      throw new Error('Invalid state parameter. Possible CSRF attack.');
    }
    
    // Exchange magic link token for access tokens
    await exchangeTokens(magicLinkToken, codeVerifier);
    
  } catch (error) {
    console.error('Callback error:', error);
    showError(error.message);
    
    // Redirect back to login
    window.location.href = '/auth/login';
  }
}

async function exchangeTokens(magicLinkToken, codeVerifier) {
  try {
    const response = await fetch('/oauth/token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      body: new URLSearchParams({
        grant_type: 'magic_link',
        token: magicLinkToken, // Note: parameter name is 'token'
        client_id: 'my-web-app-a1b2c3d4',
        code_verifier: codeVerifier,
      }),
    });
    
    const tokens = await response.json();
    
    if (response.ok) {
      // Store tokens securely
      localStorage.setItem('access_token', tokens.access_token);
      localStorage.setItem('refresh_token', tokens.refresh_token);
      localStorage.setItem('token_expires_at', Date.now() + (tokens.expires_in * 1000));
      
      // Clean up PKCE data
      PKCEManager.clearPKCE();
      
      // Redirect to authenticated area
      window.location.href = '/dashboard';
      
    } else {
      throw new Error(tokens.error_description || 'Token exchange failed');
    }
    
  } catch (error) {
    console.error('Token exchange error:', error);
    throw error;
  }
}

// Auto-run if on callback page
if (window.location.pathname === '/auth/callback') {
  handleMagicLinkCallback();
}
```

### 4. Login Page Integration

```html
<!-- login.html -->
<!DOCTYPE html>
<html>
<head>
    <title>Login - My App</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <div class="login-container">
        <h1>Welcome Back</h1>
        <form id="loginForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" required>
            </div>
            <button type="submit">Send Magic Link</button>
        </form>
        <div id="message"></div>
    </div>

    <script type="module">
        import { sendMagicLinkWithPKCE } from './auth.js';
        
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('email').value;
            await sendMagicLinkWithPKCE(email);
        });
        
        window.showMessage = (msg) => {
            document.getElementById('message').innerHTML = `<p class="success">${msg}</p>`;
        };
        
        window.showError = (msg) => {
            document.getElementById('message').innerHTML = `<p class="error">${msg}</p>`;
        };
    </script>
</body>
</html>
```

## Security Considerations

### 1. Storage Security

```javascript
// Secure token storage
class TokenManager {
  static setTokens(tokens) {
    // Use localStorage for persistence across tabs
    localStorage.setItem('access_token', tokens.access_token);
    localStorage.setItem('refresh_token', tokens.refresh_token);
    localStorage.setItem('token_expires_at', Date.now() + (tokens.expires_in * 1000));
  }
  
  static getAccessToken() {
    const token = localStorage.getItem('access_token');
    const expiresAt = localStorage.getItem('token_expires_at');
    
    if (!token || !expiresAt) return null;
    
    if (Date.now() >= parseInt(expiresAt)) {
      this.clearTokens();
      return null;
    }
    
    return token;
  }
  
  static clearTokens() {
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    localStorage.removeItem('token_expires_at');
  }
}
```

### 2. CSRF Protection

- ✅ Always use `state` parameter
- ✅ Verify state on callback
- ✅ Generate cryptographically secure random states

### 3. PKCE Best Practices

- ✅ Use `S256` challenge method (not `plain`)
- ✅ Generate cryptographically secure verifiers
- ✅ Store code_verifier securely (sessionStorage)
- ✅ Clear PKCE data after use

## Benefits Summary

| Aspect | Traditional | PKCE Web |
|--------|-------------|----------|
| Client Secret | Required in backend | ❌ Not needed |
| Frontend Security | Secret exposure risk | ✅ No secrets |
| Code Interception | Vulnerable | ✅ Protected |
| Setup Complexity | Medium | Low |
| Modern Standard | Legacy approach | ✅ Current best practice |

PKCE for web applications provides enhanced security without the complexity of managing client secrets, making it the recommended approach for modern web applications in v2.0.
