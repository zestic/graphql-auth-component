<?php

declare(strict_types=1);

namespace Tests\Integration;

use DateInterval;
use Defuse\Crypto\Key;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use Nyholm\Psr7\ServerRequest;
use Zestic\GraphQL\AuthComponent\DB\PDO\AccessTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\AuthCodeRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ClientRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\RefreshTokenRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\ScopeRepository;
use Zestic\GraphQL\AuthComponent\DB\PDO\UserRepository;
use Zestic\GraphQL\AuthComponent\Entity\ClientEntity;

class PKCEAuthorizationFlowTest extends DatabaseTestCase
{
    private AuthorizationServer $authorizationServer;

    private ClientRepository $clientRepository;

    private AuthCodeRepository $authCodeRepository;

    private AccessTokenRepository $accessTokenRepository;

    private RefreshTokenRepository $refreshTokenRepository;

    private ScopeRepository $scopeRepository;

    private UserRepository $userRepository;

    private CryptKey $privateKey;

    private Key $encryptionKey;

    private string $publicClientId;

    private string $confidentialClientId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicClientId = 'public-mobile-app';
        $this->confidentialClientId = 'confidential-web-app';

        $this->setupRepositories();
        $this->setupAuthorizationServer();
        $this->seedTestData();
    }

    public function testPKCEFlowForPublicClient(): void
    {
        // Step 1: Generate PKCE challenge
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Step 2: Create authorization request with PKCE
        $authRequest = new ServerRequest('GET', '/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->publicClientId,
            'redirect_uri' => 'myapp://callback',
            'scope' => 'read',
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
            'state' => 'random-state-value',
        ]));

        // Step 3: Validate authorization request
        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($authRequest);

        $this->assertNotNull($authorizationRequest);
        $this->assertEquals($this->publicClientId, $authorizationRequest->getClient()->getIdentifier());
        $this->assertEquals($codeChallenge, $authorizationRequest->getCodeChallenge());
        $this->assertEquals('S256', $authorizationRequest->getCodeChallengeMethod());

        // Step 4: User approves the request
        self::seedUserRepository(); // Make sure user exists
        $user = $this->userRepository->findUserById(self::$testUserId);
        $authorizationRequest->setUser($user);
        $authorizationRequest->setAuthorizationApproved(true);

        // Step 5: Complete authorization and get auth code
        $response = $this->authorizationServer->completeAuthorizationRequest($authorizationRequest, new \Nyholm\Psr7\Response());

        $this->assertEquals(302, $response->getStatusCode());
        $location = $response->getHeaderLine('Location');
        $this->assertStringContainsString('myapp://callback', $location);

        // Extract authorization code from redirect
        $query = parse_url($location, PHP_URL_QUERY);
        parse_str($query, $params);
        $this->assertArrayHasKey('code', $params);
        $authCode = $params['code'];

        // Step 6: Exchange auth code + code verifier for access token
        $tokenRequest = new ServerRequest('POST', '/token', [], http_build_query([
            'grant_type' => 'authorization_code',
            'client_id' => $this->publicClientId,
            'code' => $authCode,
            'redirect_uri' => 'myapp://callback',
            'code_verifier' => $codeVerifier,
        ]));
        $tokenRequest = $tokenRequest->withHeader('Content-Type', 'application/x-www-form-urlencoded');

        $tokenResponse = $this->authorizationServer->respondToAccessTokenRequest($tokenRequest, new \Nyholm\Psr7\Response());

        $this->assertEquals(200, $tokenResponse->getStatusCode());

        $tokenData = json_decode((string) $tokenResponse->getBody(), true);
        $this->assertArrayHasKey('access_token', $tokenData);
        $this->assertArrayHasKey('token_type', $tokenData);
        $this->assertEquals('Bearer', $tokenData['token_type']);
    }

    public function testPKCERequiredForPublicClient(): void
    {
        // Authorization request without PKCE should fail for public clients
        $authRequest = new ServerRequest('GET', '/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->publicClientId,
            'redirect_uri' => 'myapp://callback',
            'scope' => 'read',
            'state' => 'random-state-value',
            // Missing code_challenge
        ]));

        $this->expectException(\League\OAuth2\Server\Exception\OAuthServerException::class);
        $this->expectExceptionMessage('code_challenge');

        $this->authorizationServer->validateAuthorizationRequest($authRequest);
    }

    public function testConfidentialClientCanUseTraditionalFlow(): void
    {
        // Confidential clients can still use traditional flow without PKCE
        $authRequest = new ServerRequest('GET', '/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $this->confidentialClientId,
            'redirect_uri' => 'https://webapp.example.com/callback',
            'scope' => 'read write',
            'state' => 'random-state-value',
            // No PKCE parameters
        ]));

        $authorizationRequest = $this->authorizationServer->validateAuthorizationRequest($authRequest);

        $this->assertNotNull($authorizationRequest);
        $this->assertEquals($this->confidentialClientId, $authorizationRequest->getClient()->getIdentifier());
        $this->assertNull($authorizationRequest->getCodeChallenge());
    }

    private function setupRepositories(): void
    {
        $this->clientRepository = new ClientRepository(self::$pdo);
        $this->authCodeRepository = new AuthCodeRepository(self::$pdo);
        $this->accessTokenRepository = new AccessTokenRepository(self::$pdo, self::$tokenConfig);
        $this->refreshTokenRepository = new RefreshTokenRepository(self::$pdo, self::$tokenConfig);
        $this->scopeRepository = new ScopeRepository(self::$pdo);
        $this->userRepository = new UserRepository(self::$pdo);
    }

    private function setupAuthorizationServer(): void
    {
        $this->privateKey = new CryptKey(getcwd() . '/tests/resources/jwt/private.key');
        $this->encryptionKey = Key::loadFromAsciiSafeString($_ENV['OAUTH_ENCRYPTION_KEY']);

        $this->authorizationServer = new AuthorizationServer(
            $this->clientRepository,
            $this->accessTokenRepository,
            $this->scopeRepository,
            $this->privateKey,
            $this->encryptionKey
        );

        // Enable AuthCodeGrant with PKCE support
        $authCodeGrant = new AuthCodeGrant(
            $this->authCodeRepository,
            $this->refreshTokenRepository,
            new DateInterval('PT10M')
        );

        $this->authorizationServer->enableGrantType(
            $authCodeGrant,
            new DateInterval('PT1H')
        );
    }

    private function seedTestData(): void
    {
        // Create public client (mobile app)
        $publicClient = new ClientEntity();
        $publicClient->setIdentifier($this->publicClientId);
        $publicClient->setName('Mobile App');
        $publicClient->setRedirectUri('myapp://callback');
        $publicClient->setIsConfidential(false);
        $this->clientRepository->create($publicClient);

        // Create confidential client (web app)
        $confidentialClient = new ClientEntity();
        $confidentialClient->setIdentifier($this->confidentialClientId);
        $confidentialClient->setName('Web App');
        $confidentialClient->setClientSecret('web-app-secret');
        $confidentialClient->setRedirectUri('https://webapp.example.com/callback');
        $confidentialClient->setIsConfidential(true);
        $this->clientRepository->create($confidentialClient);

        // Seed scopes
        $this->seedScopeRepository();
    }

    private function seedScopeRepository(): void
    {
        $schema = self::getSchemaPrefix();

        // Insert test scopes
        self::$pdo->exec("INSERT INTO {$schema}oauth_scopes (id, description) VALUES ('read', 'Read access'), ('write', 'Write access')");

        // Insert client scopes for both clients
        self::$pdo->exec("INSERT INTO {$schema}oauth_client_scopes (client_id, scope) VALUES ('{$this->publicClientId}', 'read')");
        self::$pdo->exec("INSERT INTO {$schema}oauth_client_scopes (client_id, scope) VALUES ('{$this->confidentialClientId}', 'read')");
        self::$pdo->exec("INSERT INTO {$schema}oauth_client_scopes (client_id, scope) VALUES ('{$this->confidentialClientId}', 'write')");
    }

    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
}
