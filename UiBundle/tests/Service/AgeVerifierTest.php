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
use c975L\UiBundle\AgeVerification\GoCamProvider;
use c975L\UiBundle\Service\AgeVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

// The gate with the provider this bundle ships, go.cam, and keys drawn for the test: the provider named in config is the one used, what the site sends has to open with the partner's keys, and what comes back is only believed when signed, carrying the session's nonce, recent and a success
class AgeVerifierTest extends TestCase
{
    private string $cipherKey;

    private string $hmacKey;

    private MockClock $clock;

    private Request $request;

    protected function setUp(): void
    {
        $this->cipherKey = bin2hex(random_bytes(32));
        $this->hmacKey = bin2hex(random_bytes(128));
        $this->clock = new MockClock('2026-11-11 15:00:00');
        $this->request = new Request(server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'test-agent', 'HTTP_HOST' => 'example.com']);
        $this->request->setSession(new Session(new MockArraySessionStorage()));
        $this->request->setLocale('fr');
    }

    /**
     * @param array<string, mixed>|null $configValues
     */
    private function createVerifier(?array $configValues = null): AgeVerifier
    {
        $configValues ??= [
            'age-verification-provider' => 'gocam',
            'age-verification-gocam-partner-id' => '1234',
            'age-verification-gocam-cipher-key' => $this->cipherKey,
            'age-verification-gocam-hmac-key' => $this->hmacKey,
        ];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('hasParameter')->willReturnCallback(static fn (string $key) => \array_key_exists($key, $configValues));
        $configService->method('get')->willReturnCallback(static fn (string $key) => $configValues[$key] ?? null);

        return new AgeVerifier($configService, new RequestStack([$this->request]), $this->clock, 'kernel-secret', [new GoCamProvider($configService, $this->clock)]);
    }

    // What go.cam's server does: encrypts with the cipher key, then signs with the hmac key
    private function seal(array $data, ?string $hmacKey = null): string
    {
        $iv = random_bytes(16);
        $buffer = $iv . openssl_encrypt(json_encode($data, \JSON_THROW_ON_ERROR), 'aes-256-cbc', hex2bin($this->cipherKey), \OPENSSL_RAW_DATA, $iv);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $buffer, hex2bin($hmacKey ?? $this->hmacKey), true) . $buffer), '+/', '-_'), '=');
    }

    // What go.cam's server reads
    private function open(string $payload): array
    {
        $buffer = base64_decode(strtr($payload, '-_', '+/'), true);
        $signed = substr($buffer, 32);
        $this->assertSame(hash_hmac('sha256', $signed, hex2bin($this->hmacKey), true), substr($buffer, 0, 32));

        return json_decode(openssl_decrypt(substr($signed, 16), 'aes-256-cbc', hex2bin($this->cipherKey), \OPENSSL_RAW_DATA, substr($signed, 0, 16)), true);
    }

    // A result as go.cam sends it back, for the nonce the gate drew
    private function goCamResult(string $nonce, int $stateInt = 2, int $secondsAgo = 60): array
    {
        return [
            'requestTime' => $this->clock->now()->getTimestamp() - $secondsAgo,
            '2' => json_encode(['userId' => 0, 'nonce' => $nonce]),
            '11' => ['state' => 2 === $stateInt ? 'Success' : 'Fail', 'stateInt' => $stateInt, 'sessionId' => 1, 'errorCode' => 0],
        ];
    }

    // Draws the gate and returns the nonce it sent go.cam
    private function drawNonce(AgeVerifier $verifier): string
    {
        parse_str((string) parse_url((string) $verifier->getIframeUrl(), \PHP_URL_QUERY), $query);

        return json_decode($this->open($query['d'])['2'], true)['nonce'];
    }

    public function testDisabledUntilTheProviderIsChosenAndHoldsWellFormedKeys(): void
    {
        $keys = ['age-verification-provider' => 'gocam', 'age-verification-gocam-partner-id' => '1234', 'age-verification-gocam-cipher-key' => $this->cipherKey, 'age-verification-gocam-hmac-key' => $this->hmacKey];

        $this->assertTrue($this->createVerifier()->isEnabled());
        $this->assertSame('go.cam', $this->createVerifier()->getProvider()?->getName());
        $this->assertFalse($this->createVerifier([])->isEnabled());
        $this->assertFalse($this->createVerifier(['age-verification-provider' => ''] + $keys)->isEnabled());
        $this->assertFalse($this->createVerifier(['age-verification-provider' => 'unknown'] + $keys)->isEnabled());
        $this->assertFalse($this->createVerifier(['age-verification-gocam-hmac-key' => null] + $keys)->isEnabled());
        $this->assertFalse($this->createVerifier(['age-verification-gocam-cipher-key' => 'not-hex'] + $keys)->isEnabled());
    }

    // Only an item of age waits, and only on a site that configured go.cam
    public function testGateClosesForAnAdultItemOnly(): void
    {
        $verifier = $this->createVerifier();

        $this->assertTrue($verifier->isGateClosed(18));
        $this->assertTrue($verifier->isGateClosed('18'));
        $this->assertTrue($verifier->isGateClosed('18+'));
        $this->assertFalse($verifier->isGateClosed('3-6'));
        $this->assertFalse($verifier->isGateClosed('16+'));
        $this->assertFalse($verifier->isGateClosed('all ages'));
        $this->assertFalse($verifier->isGateClosed(16));
        $this->assertFalse($verifier->isGateClosed(null));
        $this->assertFalse($verifier->isGateClosed(''));
        $this->assertFalse($this->createVerifier([])->isGateClosed(18));
    }

    // The request opens with the partner's keys, in the visitor's language, and asks for no e-mail at all
    public function testIframeUrlCarriesASealedRequestWithoutEmail(): void
    {
        $url = (string) $this->createVerifier()->getIframeUrl();
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $data = $this->open($query['d']);

        $this->assertStringStartsWith('https://go.cam/fr/token/iframeCheck?p=1234&d=', $url);
        $this->assertSame('203.0.113.7', $data['3']);
        $this->assertSame(2, $data['10']);
        $this->assertNotContains('email', $data['9']['verificationTypeList']);
        $this->assertFalse($data['9']['useEmailAsFallbackVerification']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', json_decode($data['2'], true)['nonce']);
    }

    // Two gates on one page share a nonce: the second drawing its own would void the first's result
    public function testOneRequestDrawsOneNonce(): void
    {
        $verifier = $this->createVerifier();

        $this->assertSame($verifier->getIframeUrl(), $verifier->getIframeUrl());
    }

    // A second tab drawn after the first keeps its nonce: the visitor verifying in the first one is not refused
    public function testTwoTabsShareTheSessionNonce(): void
    {
        $session = $this->request->getSession();
        $nonce = $this->drawNonce($this->createVerifier());
        $this->request = new Request(server: ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_HOST' => 'example.com']);
        $this->request->setSession($session);
        $verifier = $this->createVerifier();

        $this->assertSame($nonce, $this->drawNonce($verifier));
        $this->assertTrue($verifier->confirm($this->seal($this->goCamResult($nonce))));
    }

    // A result read a while after the page was drawn is still an answer
    public function testAResultAQuarterOfAnHourLateIsAccepted(): void
    {
        $verifier = $this->createVerifier();

        $this->assertTrue($verifier->confirm($this->seal($this->goCamResult($this->drawNonce($verifier), 2, 1800))));
    }

    // No session to hold the nonce, no result could ever be checked: no url rather than an exception
    public function testNoIframeUrlWithoutASession(): void
    {
        $this->request = new Request();

        $this->assertNull($this->createVerifier()->getIframeUrl());
    }

    public function testFallsBackToEnglishForALanguageGoCamDoesNotSpeak(): void
    {
        $this->request->setLocale('ja');

        $this->assertStringStartsWith('https://go.cam/en/', (string) $this->createVerifier()->getIframeUrl());
    }

    public function testASignedSuccessCarryingTheNonceVerifiesTheVisitor(): void
    {
        $verifier = $this->createVerifier();
        $nonce = $this->drawNonce($verifier);

        $this->assertFalse($verifier->isVerified());
        $this->assertTrue($verifier->confirm($this->seal($this->goCamResult($nonce))));
        $this->assertTrue($verifier->isVerified());
        $this->assertFalse($verifier->isGateClosed(18));
    }

    // The nonce is spent: the same result posted twice only counts once
    public function testAResultCannotBeReplayed(): void
    {
        $verifier = $this->createVerifier();
        $payload = $this->seal($this->goCamResult($this->drawNonce($verifier)));

        $this->assertTrue($verifier->confirm($payload));
        $this->assertFalse($verifier->confirm($payload));
    }

    public function testATamperedResultIsRefused(): void
    {
        $verifier = $this->createVerifier();
        $buffer = base64_decode(strtr($this->seal($this->goCamResult($this->drawNonce($verifier))), '-_', '+/'));
        $buffer[\strlen($buffer) - 1] = \chr(\ord($buffer[\strlen($buffer) - 1]) ^ 1);

        $this->assertFalse($verifier->confirm(rtrim(strtr(base64_encode($buffer), '+/', '-_'), '=')));
        $this->assertFalse($verifier->isVerified());
    }

    public function testAResultSignedWithAnotherKeyIsRefused(): void
    {
        $verifier = $this->createVerifier();

        $this->assertFalse($verifier->confirm($this->seal($this->goCamResult($this->drawNonce($verifier)), bin2hex(random_bytes(128)))));
    }

    // Someone else's genuine result, obtained for another nonce
    public function testAResultForAnotherNonceIsRefused(): void
    {
        $verifier = $this->createVerifier();
        $this->drawNonce($verifier);

        $this->assertFalse($verifier->confirm($this->seal($this->goCamResult(bin2hex(random_bytes(16))))));
    }

    public function testAFailedVerificationIsRefused(): void
    {
        $verifier = $this->createVerifier();

        $this->assertFalse($verifier->confirm($this->seal($this->goCamResult($this->drawNonce($verifier), 3))));
    }

    public function testAStaleResultIsRefused(): void
    {
        $verifier = $this->createVerifier();

        $this->assertFalse($verifier->confirm($this->seal($this->goCamResult($this->drawNonce($verifier), 2, 7200))));
    }

    public function testGarbageIsRefused(): void
    {
        $verifier = $this->createVerifier();
        $this->drawNonce($verifier);

        $this->assertFalse($verifier->confirm('not a payload'));
        $this->assertFalse($verifier->confirm(''));
    }

    // The cookie keeps a visitor verified past the session, until it expires, and cannot be written by hand
    public function testTheCookieKeepsTheVisitorVerifiedUntilItExpires(): void
    {
        $cookie = $this->createVerifier()->createCookie();
        $this->request->cookies->set(AgeVerifier::COOKIE_NAME, $cookie->getValue());

        $this->assertTrue($cookie->isHttpOnly());
        $this->assertTrue($this->createVerifier()->isVerified());

        $this->clock->sleep(31 * 86400);
        $this->assertFalse($this->createVerifier()->isVerified());
    }

    public function testAForgedCookieIsRefused(): void
    {
        $this->request->cookies->set(AgeVerifier::COOKIE_NAME, ($this->clock->now()->getTimestamp() + 3600) . '.forged');

        $this->assertFalse($this->createVerifier()->isVerified());
    }
}
