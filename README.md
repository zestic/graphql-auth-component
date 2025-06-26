# Auth Component

A GraphQL authentication component with OAuth2 support, magic link authentication, and JWT tokens.

## Setup

### Generating OAuth2 Keys

Before using the authentication component, you need to generate the required OAuth2 keys:

```bash
composer run generate-keys
```

This script will generate:
- **JWT Private/Public Key Pair**: Used for signing and verifying JWT tokens
- **Encryption Key**: Used for encrypting authorization and refresh codes

The keys will be saved to:
- `config/jwt/private.key` - JWT private key (keep secure!)
- `config/jwt/public.key` - JWT public key
- `config/autoload/auth.local.php` - Contains only the encryption key

**Important**: The script will fail if keys already exist to prevent accidental overwriting.

### Key Generation Parameters

The script uses configurable OpenSSL parameters for JWT key generation:

- **digestAlg**: Hash algorithm (`sha256`, `sha384`, `sha512`)
  - `sha256`: Fast, widely supported (default)
  - `sha384`: More secure, good balance
  - `sha512`: Most secure, slower

- **privateKeyBits**: Key size in bits (`2048`, `3072`, `4096`)
  - `2048`: Fast, minimum recommended (default)
  - `3072`: Good security/performance balance
  - `4096`: Maximum security, slower

- **privateKeyType**: Key algorithm (`RSA`, `DSA`, `DH`, `EC`)
  - `RSA`: Most widely supported (default)
  - `EC`: Elliptic Curve, smaller keys, good performance

### Docker Support

For Docker environments, you can override the key paths using environment variables:

```bash
export JWT_PRIVATE_KEY_PATH=/app/keys/jwt/private.key
export JWT_PUBLIC_KEY_PATH=/app/keys/jwt/public.key
export AUTH_LOCAL_CONFIG_PATH=/app/config/autoload/auth.local.php
composer run generate-keys
```

### Configuration

The component uses the following default configuration structure:

```php
'auth' => [
    'jwt' => [
        'privateKeyPath' => 'config/jwt/private.key',
        'publicKeyPath'  => 'config/jwt/public.key',
        'passphrase'     => null, // Set via environment if needed
        'keyGeneration' => [
            'digestAlg'       => 'sha256',     // sha256, sha384, sha512
            'privateKeyBits'  => 2048,        // 2048, 3072, 4096
            'privateKeyType'  => 'RSA',       // RSA, DSA, DH, EC
        ],
    ],
    'token' => [
        'accessTokenTtl'  => 60,    // 1 hour (in minutes)
        'loginTtl'        => 10,    // 10 minutes
        'refreshTokenTtl' => 10080, // 1 week (in minutes)
        'registrationTtl' => 1440,  // 24 hours (in minutes)
    ],
]
```

## Notifications

Need to create a class that implements SendVerificationEmailInterface and configure it

