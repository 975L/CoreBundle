<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\SecurityMisconfigurationHealthCheckProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SecurityProbeClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityMisconfigurationHealthCheckProviderTest extends TestCase
{
    // What a correctly deployed site answers on its root: a page, and nothing else worth reporting
    private const CLEAN_ROOT = [
        'status' => 200,
        'headers' => ['content-type' => ['text/html; charset=UTF-8']],
        'body' => '<html><body>Home</body></html>',
    ];

    // What every probed path answers when it is not served
    private const NOT_FOUND = ['status' => 404, 'headers' => [], 'body' => ''];

    // A real resolver rather than a stub, so these tests assert the very url the dashboard will group the row under (see SiteUrlResolver::siteRoot())
    private function createUrlResolver(?string $siteUrl = 'https://example.com'): SiteUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new SiteUrlResolver($configService);
    }

    private function createTranslator(): TranslatorInterface
    {
        // The parameters are appended rather than substituted: the catalogue is not loaded here, so a placeholder-carrying key would swallow the very values these tests are about (the paths served, the flags missing)
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = [], ?string $domain = null) => $id . ($parameters ? ' ' . implode(' ', $parameters) : '')
        );

        return $translator;
    }

    // $responses maps a url to what probing it answers; anything else answers 404
    private function createClient(array $responses = []): SecurityProbeClient
    {
        $responses += ['https://example.com/' => self::CLEAN_ROOT];

        $client = $this->createStub(SecurityProbeClient::class);
        $client->method('probe')->willReturnCallback(
            fn (string $url) => $responses[$url] ?? self::NOT_FOUND
        );

        return $client;
    }

    private function createProvider(array $responses = [], ?string $siteUrl = 'https://example.com'): SecurityMisconfigurationHealthCheckProvider
    {
        return new SecurityMisconfigurationHealthCheckProvider(
            $this->createClient($responses),
            $this->createUrlResolver($siteUrl),
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsSecurityMisconfig(): void
    {
        $this->assertSame('security-misconfig', $this->createProvider()->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $this->assertSame([], $this->createProvider([], null)->runChecks());
    }

    public function testRunChecksStatusIsOkOnACorrectlyDeployedSite(): void
    {
        $result = $this->createProvider()->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame([], $result['details']['issues']);
        // The site root's canonical form, the same url every other site-wide check records
        $this->assertSame('https://example.com/', $result['url']);
    }

    public function testRunChecksFlagsAReachableProfiler(): void
    {
        $result = $this->createProvider([
            'https://example.com/_profiler' => ['status' => 200, 'headers' => [], 'body' => 'Symfony Profiler'],
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('/_profiler', $result['summary']);
    }

    // A site sending its debug paths back to the home page is not exposing them - the redirect is the answer, which is why it is never followed
    public function testRunChecksIgnoresADebugPathAnsweringARedirect(): void
    {
        $result = $this->createProvider([
            'https://example.com/_profiler' => ['status' => 302, 'headers' => ['location' => ['https://example.com/']], 'body' => ''],
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    // The same trap as the sensitive files below, on the paths whose mere 200 used to be enough: a catch-all route answers the profiler's url with the home page, which is not a profiler
    public function testRunChecksIgnoresACatchAllPageAnsweringForADebugPath(): void
    {
        $result = $this->createProvider([
            'https://example.com/_profiler' => self::CLEAN_ROOT,
            'https://example.com/_wdt/latest' => self::CLEAN_ROOT,
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    public function testRunChecksFlagsTheProfilerTokenLeftOnTheResponse(): void
    {
        $root = self::CLEAN_ROOT;
        $root['headers']['x-debug-token'] = ['a1b2c3'];

        $result = $this->createProvider(['https://example.com/' => $root])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('label.health_check_security_misconfig_debug_token', $result['summary']);
    }

    public function testRunChecksFlagsASensitiveFileTheServerActuallyServes(): void
    {
        $result = $this->createProvider([
            'https://example.com/.env' => ['status' => 200, 'headers' => ['content-type' => ['text/plain']], 'body' => "APP_ENV=prod\nAPP_SECRET=x"],
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertStringContainsString('/.env', $result['summary']);
    }

    // The trap this check exists to avoid: a site whose catch-all route answers 200 with its own html to every unknown path
    public function testRunChecksIgnoresACatchAllPageAnsweringForASensitiveFile(): void
    {
        $result = $this->createProvider([
            'https://example.com/.env' => self::CLEAN_ROOT,
            'https://example.com/composer.json' => self::CLEAN_ROOT,
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    public function testRunChecksFlagsADirectoryListing(): void
    {
        $result = $this->createProvider([
            'https://example.com/vendor/' => ['status' => 200, 'headers' => ['content-type' => ['text/html']], 'body' => '<h1>Index of /vendor/</h1>'],
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('/vendor/', $result['summary']);
    }

    // A real listing is html and says "Index of ": a 200 whose content type is anything else proves the opposite, and a directory answering one on every deployment would keep the row orange forever
    public function testRunChecksIgnoresADirectoryAnsweringWithoutAListing(): void
    {
        $result = $this->createProvider([
            'https://example.com/vendor/' => ['status' => 200, 'headers' => [], 'body' => 'Forbidden'],
        ])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    public function testRunChecksFlagsASessionCookieMissingItsFlags(): void
    {
        $root = self::CLEAN_ROOT;
        $root['headers']['set-cookie'] = ['PHPSESSID=abc; path=/; HttpOnly'];

        $result = $this->createProvider(['https://example.com/' => $root])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('secure, samesite', $result['summary']);
    }

    public function testRunChecksAcceptsAFullyFlaggedSessionCookie(): void
    {
        $root = self::CLEAN_ROOT;
        $root['headers']['set-cookie'] = ['PHPSESSID=abc; path=/; secure; httponly; samesite=lax'];

        $this->assertSame(HealthCheckResult::STATUS_OK, $this->createProvider(['https://example.com/' => $root])->runChecks()[0]['status']);
    }

    // Only the session cookie is judged: a consent or locale cookie has no session to steal
    public function testRunChecksIgnoresANonSessionCookie(): void
    {
        $root = self::CLEAN_ROOT;
        $root['headers']['set-cookie'] = ['locale=fr; path=/'];

        $this->assertSame(HealthCheckResult::STATUS_OK, $this->createProvider(['https://example.com/' => $root])->runChecks()[0]['status']);
    }

    public function testRunChecksFlagsThePoweredByBanner(): void
    {
        $root = self::CLEAN_ROOT;
        $root['headers']['x-powered-by'] = ['PHP/8.4.3'];

        $result = $this->createProvider(['https://example.com/' => $root])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('PHP/8.4.3', $result['summary']);
    }

    // A managed host rarely lets its "Server" header be touched: reported in the summary, never turning the row orange for something the site cannot fix
    public function testRunChecksReportsAVersionedServerBannerWithoutDegradingTheStatus(): void
    {
        $root = self::CLEAN_ROOT;
        $root['headers']['server'] = ['nginx/1.24.0'];

        $result = $this->createProvider(['https://example.com/' => $root])->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertStringContainsString('nginx/1.24.0', $result['summary']);
        $this->assertSame('info', $result['details']['issues'][0]['severity']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheRootCallFails(): void
    {
        $client = $this->createStub(SecurityProbeClient::class);
        $client->method('probe')->willThrowException(new \RuntimeException('Connection refused'));

        $provider = new SecurityMisconfigurationHealthCheckProvider($client, $this->createUrlResolver(), $this->createTranslator());
        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Connection refused'], $result['details']);
    }
}
