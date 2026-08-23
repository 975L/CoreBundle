<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Service;

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

    private function createProvider(): GoogleOAuthLoginProvider
    {
        return new GoogleOAuthLoginProvider(new MockHttpClient());
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
        $provider = $this->createProvider();

        $this->assertTrue(new OAuthLoginClient(new MockHttpClient(), $this->createConfigService($this->credentials()))->isConfigured($provider));
        $this->assertFalse(new OAuthLoginClient(new MockHttpClient(), $this->createConfigService([]))->isConfigured($provider));
        $this->assertFalse(new OAuthLoginClient(new MockHttpClient(), $this->createConfigService([
            'login-google-oauth-client-id' => 'an-id.apps.googleusercontent.com',
        ]))->isConfigured($provider));
    }

    // A credential pasted from a console carries whatever whitespace came with it, and an empty string is not a value
    public function testABlankCredentialCountsAsNoCredential(): void
    {
        $client = new OAuthLoginClient(new MockHttpClient(), $this->createConfigService([
            'login-google-oauth-client-id' => '  ',
            'login-google-oauth-client-secret' => 'a-secret',
        ]));

        $this->assertFalse($client->isConfigured($this->createProvider()));
    }

    public function testTheAuthorizationUrlCarriesTheStateAndTheScopeTheProviderAsksFor(): void
    {
        $client = new OAuthLoginClient(new MockHttpClient(), $this->createConfigService($this->credentials()));

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

    public function testACodeIsTradedForItsAccessToken(): void
    {
        $sent = [];
        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options) use (&$sent): MockResponse {
            $sent = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return new MockResponse(json_encode(['access_token' => 'an-access-token']), ['http_code' => 200]);
        });

        $token = new OAuthLoginClient($httpClient, $this->createConfigService($this->credentials()))
            ->exchangeCodeForAccessToken($this->createProvider(), 'a-code', 'https://example.test/connect/google/check');

        $this->assertSame('an-access-token', $token);
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
        $configService = $this->createConfigService($this->credentials());

        $refused = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode(['error' => 'invalid_grant']), ['http_code' => 400]));
        $this->assertNull(new OAuthLoginClient($refused, $configService)->exchangeCodeForAccessToken($this->createProvider(), 'a-code', 'https://example.test/check'));

        $garbled = new MockHttpClient(static fn (): MockResponse => new MockResponse('<html>gateway timeout</html>', ['http_code' => 200]));
        $this->assertNull(new OAuthLoginClient($garbled, $configService)->exchangeCodeForAccessToken($this->createProvider(), 'a-code', 'https://example.test/check'));
    }
}
