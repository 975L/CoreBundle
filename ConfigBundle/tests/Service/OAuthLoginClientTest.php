<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

use c975L\ConfigBundle\Security\AppleOAuthLoginProvider;
use c975L\ConfigBundle\Security\GoogleOAuthLoginProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\OAuthLoginClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OAuthLoginClientTest extends TestCase
{
    /**
     * @param array<string, mixed> $configs
     */
    private function createConfigService(array $configs): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug): mixed => $configs[$slug] ?? null);

        return $configService;
    }

    private function createProvider(?array $configs = null): GoogleOAuthLoginProvider
    {
        return new GoogleOAuthLoginProvider(new MockHttpClient(), $this->createConfigService($configs ?? $this->credentials()));
    }

    private function credentials(): array
    {
        return [
            'login-google-oauth-client-id' => 'an-id.apps.googleusercontent.com',
            'login-google-oauth-client-secret' => 'a-secret',
        ];
    }

    public function testAProviderIsConfiguredOnlyWhenBothCredentialsAreFilled(): void
    {
        $client = new OAuthLoginClient(new MockHttpClient());

        $this->assertTrue($client->isConfigured($this->createProvider()));
        $this->assertFalse($client->isConfigured($this->createProvider([])));
        $this->assertFalse($client->isConfigured($this->createProvider([
            'login-google-oauth-client-id' => 'an-id.apps.googleusercontent.com',
        ])));
    }

    // A credential pasted from a console carries whatever whitespace came with it, and an empty string is not a value
    public function testABlankCredentialCountsAsNoCredential(): void
    {
        $this->assertFalse(new OAuthLoginClient(new MockHttpClient())->isConfigured($this->createProvider([
            'login-google-oauth-client-id' => '  ',
            'login-google-oauth-client-secret' => 'a-secret',
        ])));
    }

    public function testTheAuthorizationUrlCarriesTheStateAndTheScopeTheProviderAsksFor(): void
    {
        $client = new OAuthLoginClient(new MockHttpClient());

        $url = $client->getAuthorizationUrl($this->createProvider(), 'https://example.test/connect/google/check', 'a-state');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertStringStartsWith(GoogleOAuthLoginProvider::AUTHORIZATION_ENDPOINT, $url);
        $this->assertSame('an-id.apps.googleusercontent.com', $query['client_id']);
        $this->assertSame('https://example.test/connect/google/check', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('openid email', $query['scope']);
        $this->assertSame('a-state', $query['state']);
        // A login wants the account, not a durable authorization to act for it later
        $this->assertArrayNotHasKey('access_type', $query);
    }

    // What a provider adds of its own goes into the url too: Apple asking for its answer to be posted back
    public function testTheAuthorizationUrlCarriesTheProvidersOwnParameters(): void
    {
        $provider = new AppleOAuthLoginProvider($this->createConfigService(['login-apple-services-id' => 'com.example.login']));

        $url = new OAuthLoginClient(new MockHttpClient())->getAuthorizationUrl($provider, 'https://example.test/connect/apple/check', 'a-state');

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('form_post', $query['response_mode']);
        $this->assertSame('com.example.login', $query['client_id']);
    }

    public function testACodeIsTradedForTheProvidersTokens(): void
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse(json_encode(['access_token' => 'an-access-token', 'id_token' => 'an-id-token']), ['http_code' => 200]);
        });

        $token = new OAuthLoginClient($httpClient)
            ->exchangeCodeForToken($this->createProvider(), 'a-code', 'https://example.test/connect/google/check');

        // Handed whole to the provider: Apple reads the id_token, Google the access token
        $this->assertSame(['access_token' => 'an-access-token', 'id_token' => 'an-id-token'], $token);
        $this->assertSame('POST', $sent['method']);
        $this->assertSame(GoogleOAuthLoginProvider::TOKEN_ENDPOINT, $sent['url']);
        parse_str($sent['body'], $body);
        $this->assertSame('a-code', $body['code']);
        $this->assertSame('authorization_code', $body['grant_type']);
        // Providers check the code is traded for the very uri it was issued for
        $this->assertSame('https://example.test/connect/google/check', $body['redirect_uri']);
        $this->assertSame('a-secret', $body['client_secret']);
    }

    // A replayed code, a revoked authorization, a provider that is down: none of them is worth an exception reaching the visitor, the callback turning a null into "the sign-in didn't complete"
    public function testARefusedOrUnreadableTokenRequestReturnsNothing(): void
    {
        $refused = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['error' => 'invalid_grant']), ['http_code' => 400]));
        $this->assertNull(new OAuthLoginClient($refused)->exchangeCodeForToken($this->createProvider(), 'a-code', 'https://example.test/check'));

        $garbled = new MockHttpClient(static fn (): MockResponse => new MockResponse('<html>gateway timeout</html>', ['http_code' => 200]));
        $this->assertNull(new OAuthLoginClient($garbled)->exchangeCodeForToken($this->createProvider(), 'a-code', 'https://example.test/check'));

        $tokenless = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['token_type' => 'Bearer']), ['http_code' => 200]));
        $this->assertNull(new OAuthLoginClient($tokenless)->exchangeCodeForToken($this->createProvider(), 'a-code', 'https://example.test/check'));
    }
}
