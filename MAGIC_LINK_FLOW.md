Based on the test and the ANALYSIS.md approach, here's exactly what the server's GET request to the client callback should look like:

## 🔄 **Server → Client Callback Request**

### **HTTP Request Format**

```http
GET https://app.example.com/callback?code=oauth-code-from-magic-link-validation&state=secure-magic-link-state HTTP/1.1
Host: app.example.com
User-Agent: YourServer/1.0
```

### **URL Parameters Breakdown**

```javascript
// Base callback URL (from original GraphQL mutation)
const callbackUrl = "https://app.example.com/callback";

// Query parameters the server should add
const params = {
  code: "oauth-code-from-magic-link-validation",  // Server-generated OAuth authorization code
  state: "secure-magic-link-state"                // Original state from GraphQL mutation
};

// Final URL
const fullCallbackUrl = `${callbackUrl}?code=${params.code}&state=${params.state}`;
```

### **Complete Example URL**

```
https://app.example.com/callback?code=oauth-code-from-magic-link-validation&state=secure-magic-link-state
```

## 🔍 **Server-Side Logic Flow**

Here's what your server should do when the user clicks the magic link:

### **1. User Clicks Magic Link**
```
https://yourserver.com/magic-link?token=magic-link-token-xyz
```

### **2. Server Validates Magic Link Token**
```javascript
// Server receives magic link click
const magicLinkToken = request.query.token; // "magic-link-token-xyz"

// Validate token (check expiration, single-use, etc.)
const magicLinkData = await validateMagicLinkToken(magicLinkToken);

if (!magicLinkData.valid) {
  // Redirect to error page
  return redirect('/auth/error?error=invalid_token');
}
```

### **3. Server Retrieves Stored PKCE Data**
```javascript
// Retrieve the PKCE data that was stored when GraphQL mutation was sent
const storedData = await getMagicLinkData(magicLinkToken);

// storedData should contain:
// {
//   email: "user@example.com",
//   codeChallenge: "magic-link-challenge-xyz", 
//   codeChallengeMethod: "S256",
//   state: "secure-magic-link-state",
//   redirectUri: "https://app.example.com/callback",
//   expiresAt: "2024-01-01T12:00:00Z"
// }
```

### **4. Server Generates OAuth Authorization Code**
```javascript
// Generate a standard OAuth authorization code
const authorizationCode = generateAuthorizationCode(); // "oauth-code-from-magic-link-validation"

// Store the authorization code with associated PKCE data for later token exchange
await storeAuthorizationCode(authorizationCode, {
  email: storedData.email,
  codeChallenge: storedData.codeChallenge,
  codeChallengeMethod: storedData.codeChallengeMethod,
  redirectUri: storedData.redirectUri,
  expiresAt: Date.now() + (10 * 60 * 1000) // 10 minutes
});

// Mark magic link token as used (prevent replay)
await markMagicLinkTokenAsUsed(magicLinkToken);
```

### **5. Server Calls Client Callback**
```javascript
// Build callback URL with OAuth parameters
const callbackUrl = new URL(storedData.redirectUri);
callbackUrl.searchParams.set('code', authorizationCode);
callbackUrl.searchParams.set('state', storedData.state);

// Redirect user to client callback
return redirect(callbackUrl.toString());
// Results in: https://app.example.com/callback?code=oauth-code-from-magic-link-validation&state=secure-magic-link-state
```

## 🔒 **Security Considerations**

### **Authorization Code Properties**
- ✅ **Single-use**: Code becomes invalid after token exchange
- ✅ **Time-limited**: Expires in 10 minutes (OAuth 2.0 recommendation)
- ✅ **Cryptographically secure**: Random, unpredictable value
- ✅ **Bound to PKCE**: Associated with original code challenge

### **State Parameter**
- ✅ **Unchanged**: Must match exactly what was sent in GraphQL mutation
- ✅ **CSRF protection**: Client validates this matches stored state
- ✅ **Cryptographically secure**: Original state was randomly generated

## 🎯 **What Happens Next**

After the server makes this callback request:

1. **Client receives callback** with `code` and `state` parameters
2. **Client validates state** matches stored value (CSRF protection)
3. **Client exchanges authorization code** for tokens using PKCE verifier:
   ```javascript
   POST /oauth/token
   {
     grant_type: "authorization_code",
     code: "oauth-code-from-magic-link-validation", 
     code_verifier: "magic-link-verifier-xyz",
     client_id: "your-client-id",
     redirect_uri: "https://app.example.com/callback"
   }
   ```

This approach ensures the magic link flow uses **standard OAuth 2.0 security patterns** while providing the **passwordless UX** of magic links!
