<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Captcha;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Captcha\RecaptchaProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RecaptchaProviderTest extends TestCase
{
    /**
     * @param array<string, mixed> $configValues
     */
    private function createProvider(array $configValues, ?MockHttpClient $httpClient = null): RecaptchaProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key) => array_key_exists($key, $configValues));
        $configService->method('get')->willReturnCallback(static fn (string $key) => $configValues[$key] ?? null);

        return new RecaptchaProvider($httpClient ?? new MockHttpClient(), $configService);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createClient(array $payload, int $statusCode = 200): MockHttpClient
    {
        return new MockHttpClient(new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['http_code' => $statusCode]));
    }

    private const array KEYS = [
        'captcha-recaptcha-site-key' => 'site-key',
        'captcha-recaptcha-secret-key' => 'secret-key',
    ];

    public function testIsConfiguredOnlyWhenBothKeysAreSet(): void
    {
        $this->assertTrue($this->createProvider(self::KEYS)->isConfigured());
        $this->assertFalse($this->createProvider(['captcha-recaptcha-site-key' => 'site-key'])->isConfigured());
        $this->assertFalse($this->createProvider(['captcha-recaptcha-secret-key' => 'secret-key'])->isConfigured());
        $this->assertFalse($this->createProvider([])->isConfigured());
    }

    // An emptied config field comes back as '', which is no more a key than an unseeded one
    public function testIsConfiguredIgnoresEmptyKeys(): void
    {
        $this->assertFalse($this->createProvider(['captcha-recaptcha-site-key' => '', 'captcha-recaptcha-secret-key' => ''])->isConfigured());
    }

    public function testGetSiteKeyReturnsTheConfiguredValue(): void
    {
        $this->assertSame('site-key', $this->createProvider(self::KEYS)->getSiteKey());
        $this->assertNull($this->createProvider([])->getSiteKey());
    }

    // The same threshold config/configs.json seeds, so a site whose configs were never loaded is scored exactly as one whose were
    public function testScoreThresholdDefaultsToTheSeededValue(): void
    {
        $this->assertSame(0.05, $this->createProvider(self::KEYS)->getScoreThreshold());
    }

    // 0 is a deliberate, valid threshold (accept everything), not "unset"
    public function testScoreThresholdOfZeroIsHonoured(): void
    {
        $this->assertSame(0.0, $this->createProvider([...self::KEYS, 'captcha-recaptcha-score-threshold' => '0'])->getScoreThreshold());
    }

    public function testVerifyAcceptsASuccessfulTokenScoringAboveTheThreshold(): void
    {
        $provider = $this->createProvider(self::KEYS, $this->createClient(['success' => true, 'score' => 0.9]));

        $this->assertTrue($provider->verify('token', null));
    }

    public function testVerifyRejectsATokenScoringBelowTheThreshold(): void
    {
        $provider = $this->createProvider(self::KEYS, $this->createClient(['success' => true, 'score' => 0.01]));

        $this->assertFalse($provider->verify('token', null));
    }

    // Scored against a threshold stricter than the seeded one, so the assertion says the configured value was read and not merely that 0.2 clears 0.05 on its own
    public function testVerifyHonoursAConfiguredThreshold(): void
    {
        $config = [...self::KEYS, 'captcha-recaptcha-score-threshold' => '0.5'];
        $provider = $this->createProvider($config, $this->createClient(['success' => true, 'score' => 0.2]));

        $this->assertFalse($provider->verify('token', null));
    }

    public function testVerifyRejectsAnUnsuccessfulToken(): void
    {
        $provider = $this->createProvider(self::KEYS, $this->createClient(['success' => false, 'error-codes' => ['timeout-or-duplicate']]));

        $this->assertFalse($provider->verify('token', null));
    }

    // A v2 key answers without a score - the success flag is then all there is to go on
    public function testVerifyAcceptsASuccessfulAnswerCarryingNoScore(): void
    {
        $provider = $this->createProvider(self::KEYS, $this->createClient(['success' => true]));

        $this->assertTrue($provider->verify('token', null));
    }

    public function testVerifyRejectsAnEmptyToken(): void
    {
        $provider = $this->createProvider(self::KEYS, $this->createClient(['success' => true, 'score' => 0.9]));

        $this->assertFalse($provider->verify('', null));
    }

    // Fails closed: Google unreachable or answering garbage rejects the submission rather than waving it through
    public function testVerifyRejectsWhenTheAnswerIsNotUsableJson(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('<html>502</html>', ['http_code' => 502]));
        $provider = $this->createProvider(self::KEYS, $httpClient);

        $this->assertFalse($provider->verify('token', null));
    }

    public function testVerifyPostsTheSecretTokenAndClientIpToGoogle(): void
    {
        $sent = [];
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$sent): MockResponse {
            // HttpClient normalises an array body into a urlencoded string before it reaches the callback
            parse_str($options['body'], $body);
            $sent = ['method' => $method, 'url' => $url, 'body' => $body];

            return new MockResponse(json_encode(['success' => true, 'score' => 0.9], \JSON_THROW_ON_ERROR));
        });

        $this->createProvider(self::KEYS, $httpClient)->verify('the-token', '203.0.113.7');

        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://www.google.com/recaptcha/api/siteverify', $sent['url']);
        $this->assertSame('secret-key', $sent['body']['secret']);
        $this->assertSame('the-token', $sent['body']['response']);
        $this->assertSame('203.0.113.7', $sent['body']['remoteip']);
    }

    public function testVerifyRejectsWhenNoSecretKeyIsConfigured(): void
    {
        $provider = $this->createProvider(['captcha-recaptcha-site-key' => 'site-key'], $this->createClient(['success' => true, 'score' => 0.9]));

        $this->assertFalse($provider->verify('token', null));
    }
}
