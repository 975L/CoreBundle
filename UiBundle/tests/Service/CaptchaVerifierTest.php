<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\CaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class CaptchaVerifierTest extends TestCase
{
    /**
     * @param array<string, mixed> $configValues
     */
    private function createVerifier(array $configValues, ?MockHttpClient $httpClient = null, string $clientIp = '203.0.113.7'): CaptchaVerifier
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key) => array_key_exists($key, $configValues));
        $configService->method('get')->willReturnCallback(static fn (string $key) => $configValues[$key] ?? null);

        $requestStack = new RequestStack();
        $requestStack->push(new Request(server: ['REMOTE_ADDR' => $clientIp]));

        return new CaptchaVerifier($httpClient ?? new MockHttpClient(), $configService, $requestStack);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createClient(array $payload, int $statusCode = 200): MockHttpClient
    {
        return new MockHttpClient(new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['http_code' => $statusCode]));
    }

    private const KEYS = [
        'recaptcha3-site-key' => 'site-key',
        'recaptcha3-secret-key' => 'secret-key',
    ];

    public function testIsEnabledOnlyWhenBothKeysAreSet(): void
    {
        $this->assertTrue($this->createVerifier(self::KEYS)->isEnabled());
        $this->assertFalse($this->createVerifier(['recaptcha3-site-key' => 'site-key'])->isEnabled());
        $this->assertFalse($this->createVerifier(['recaptcha3-secret-key' => 'secret-key'])->isEnabled());
        $this->assertFalse($this->createVerifier([])->isEnabled());
    }

    // An emptied config field comes back as '', which is no more a key than an unseeded one
    public function testIsEnabledIgnoresEmptyKeys(): void
    {
        $this->assertFalse($this->createVerifier(['recaptcha3-site-key' => '', 'recaptcha3-secret-key' => ''])->isEnabled());
    }

    public function testGetSiteKeyReturnsTheConfiguredValue(): void
    {
        $this->assertSame('site-key', $this->createVerifier(self::KEYS)->getSiteKey());
        $this->assertNull($this->createVerifier([])->getSiteKey());
    }

    // The same threshold config/configs.json seeds, so a site whose configs were never loaded is scored exactly as one whose were
    public function testScoreThresholdDefaultsToTheSeededValue(): void
    {
        $this->assertSame(0.05, $this->createVerifier(self::KEYS)->getScoreThreshold());
    }

    // 0 is a deliberate, valid threshold (accept everything), not "unset"
    public function testScoreThresholdOfZeroIsHonoured(): void
    {
        $this->assertSame(0.0, $this->createVerifier([...self::KEYS, 'recaptcha3-score-threshold' => '0'])->getScoreThreshold());
    }

    public function testVerifyAcceptsASuccessfulTokenScoringAboveTheThreshold(): void
    {
        $verifier = $this->createVerifier(self::KEYS, $this->createClient(['success' => true, 'score' => 0.9]));

        $this->assertTrue($verifier->verify('token'));
    }

    public function testVerifyRejectsATokenScoringBelowTheThreshold(): void
    {
        $verifier = $this->createVerifier(self::KEYS, $this->createClient(['success' => true, 'score' => 0.01]));

        $this->assertFalse($verifier->verify('token'));
    }

    // Scored against a threshold stricter than the seeded one, so the assertion says the configured value was read and not merely that 0.2 clears 0.05 on its own
    public function testVerifyHonoursAConfiguredThreshold(): void
    {
        $config = [...self::KEYS, 'recaptcha3-score-threshold' => '0.5'];
        $verifier = $this->createVerifier($config, $this->createClient(['success' => true, 'score' => 0.2]));

        $this->assertFalse($verifier->verify('token'));
    }

    public function testVerifyRejectsAnUnsuccessfulToken(): void
    {
        $verifier = $this->createVerifier(self::KEYS, $this->createClient(['success' => false, 'error-codes' => ['timeout-or-duplicate']]));

        $this->assertFalse($verifier->verify('token'));
    }

    // A v2 key answers without a score - the success flag is then all there is to go on
    public function testVerifyAcceptsASuccessfulAnswerCarryingNoScore(): void
    {
        $verifier = $this->createVerifier(self::KEYS, $this->createClient(['success' => true]));

        $this->assertTrue($verifier->verify('token'));
    }

    public function testVerifyRejectsAnEmptyOrMissingToken(): void
    {
        $verifier = $this->createVerifier(self::KEYS, $this->createClient(['success' => true, 'score' => 0.9]));

        $this->assertFalse($verifier->verify(null));
        $this->assertFalse($verifier->verify(''));
    }

    // Fails closed: Google unreachable or answering garbage rejects the submission rather than waving it through
    public function testVerifyRejectsWhenTheAnswerIsNotUsableJson(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('<html>502</html>', ['http_code' => 502]));
        $verifier = $this->createVerifier(self::KEYS, $httpClient);

        $this->assertFalse($verifier->verify('token'));
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

        $this->createVerifier(self::KEYS, $httpClient)->verify('the-token');

        $this->assertSame('POST', $sent['method']);
        $this->assertSame('https://www.google.com/recaptcha/api/siteverify', $sent['url']);
        $this->assertSame('secret-key', $sent['body']['secret']);
        $this->assertSame('the-token', $sent['body']['response']);
        $this->assertSame('203.0.113.7', $sent['body']['remoteip']);
    }

    public function testVerifyRejectsWhenNoSecretKeyIsConfigured(): void
    {
        $verifier = $this->createVerifier(['recaptcha3-site-key' => 'site-key'], $this->createClient(['success' => true, 'score' => 0.9]));

        $this->assertFalse($verifier->verify('token'));
    }
}
