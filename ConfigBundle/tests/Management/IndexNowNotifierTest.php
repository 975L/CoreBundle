<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\IndexNowNotifier;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\IndexNowClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class IndexNowNotifierTest extends TestCase
{
    private string $projectDir;

    private string $keyFile;

    // What the urls handed over look like, as SitemapWriter::getDeclaredUrls() returns them
    private const array URLS = [
        'https://example.com/' => null,
        'https://example.com/page' => '2026-10-01',
    ];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-indexnow-notifier-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
        $this->keyFile = $this->projectDir . '/public/' . IndexNowNotifier::KEY_FILE;
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    private function createConfigService(array $overrides = []): ConfigServiceInterface
    {
        $configs = $overrides + ['site-url' => 'https://example.com/', 'seo-indexnow' => true, 'seo-robots-private' => false];
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(static fn (string $key): mixed => $configs[$key] ?? null);

        return $service;
    }

    // Records every batch the notifier submits, so a test reads what went rather than counting calls
    private function createNotifier(array &$batches, array $configs = [], string $environment = 'prod', ?\Throwable $failure = null): IndexNowNotifier
    {
        $client = $this->createStub(IndexNowClient::class);
        $client->method('submit')->willReturnCallback(function (string $host, string $key, string $keyLocation, array $urls) use (&$batches, $failure): void {
            if (null !== $failure) {
                throw $failure;
            }
            $batches[] = ['host' => $host, 'key' => $key, 'keyLocation' => $keyLocation, 'urls' => $urls];
        });

        return new IndexNowNotifier($this->createConfigService($configs), $client, 'secret', $this->projectDir, $environment);
    }

    // A site announcing itself for the first time has every url to submit, the key file being written before the request since the engines fetch it to check the key
    public function testFirstRunSubmitsEveryUrlAndWritesTheKeyFile(): void
    {
        $batches = [];

        $this->assertSame(2, $this->createNotifier($batches)->notify(self::URLS));

        $this->assertCount(1, $batches);
        $this->assertSame('example.com', $batches[0]['host']);
        $this->assertSame('https://example.com/' . IndexNowNotifier::KEY_FILE, $batches[0]['keyLocation']);
        $this->assertSame(array_keys(self::URLS), $batches[0]['urls']);
        $this->assertSame($batches[0]['key'], file_get_contents($this->keyFile));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $batches[0]['key']);
    }

    // Submitting unchanged urls night after night is what gets a site's submissions throttled
    public function testNothingIsSubmittedWhenNothingChanged(): void
    {
        $batches = [];
        $this->createNotifier($batches)->notify(self::URLS);
        $batches = [];

        $this->assertSame(0, $this->createNotifier($batches)->notify(self::URLS));
        $this->assertSame([], $batches);
    }

    // New urls, a moved lastmod and a url gone from the sitemaps all go - but not the url declaring no date, which SitemapWriter would otherwise date from every run
    public function testOnlyNewChangedAndRemovedUrlsAreSubmitted(): void
    {
        $batches = [];
        $this->createNotifier($batches)->notify(self::URLS + ['https://example.com/old' => '2026-01-01']);
        $batches = [];

        $submitted = $this->createNotifier($batches)->notify([
            'https://example.com/' => null,
            'https://example.com/page' => '2026-10-30',
            'https://example.com/new' => null,
        ]);

        $this->assertSame(3, $submitted);
        $this->assertSame(['https://example.com/page', 'https://example.com/new', 'https://example.com/old'], $batches[0]['urls']);
    }

    // A refused submission leaves the snapshot untouched, so the same urls go again on the next run instead of being taken for submitted
    public function testAFailedSubmissionIsRetriedOnTheNextRun(): void
    {
        $batches = [];
        try {
            $this->createNotifier($batches, [], 'prod', new \RuntimeException('IndexNow answered 429.'))->notify(self::URLS);
            $this->fail('The refusal should have been thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('IndexNow answered 429.', $e->getMessage());
        }

        $this->assertSame(2, $this->createNotifier($batches)->notify(self::URLS));
    }

    // IndexNow refuses a whole batch holding one url of another host
    public function testUrlsOfAnotherHostAreLeftOut(): void
    {
        $batches = [];

        $this->assertSame(1, $this->createNotifier($batches)->notify(['https://example.com/' => null, 'https://cdn.example.org/file' => null]));
        $this->assertSame(['https://example.com/'], $batches[0]['urls']);
    }

    // A dev copy shares the production site-url, and would announce pages that don't exist online
    public function testNothingHappensOutsideProduction(): void
    {
        $batches = [];

        $this->assertNull($this->createNotifier($batches, [], 'dev')->notify(self::URLS));
        $this->assertSame([], $batches);
        $this->assertFileDoesNotExist($this->keyFile);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function provideInapplicableConfigs(): iterable
    {
        yield 'turned off' => [['seo-indexnow' => false]];
        yield 'out of search engines' => [['seo-robots-private' => true]];
        yield 'no site-url' => [['site-url' => '']];
    }

    // The key file a previous run left goes too, so nothing keeps vouching for submissions that no longer happen
    #[DataProvider('provideInapplicableConfigs')]
    public function testNothingIsSubmittedAndTheKeyFileIsRemovedWhenIndexNowDoesNotApply(array $configs): void
    {
        file_put_contents($this->keyFile, 'previous');
        $batches = [];

        $this->assertNull($this->createNotifier($batches, $configs)->notify(self::URLS));
        $this->assertSame([], $batches);
        $this->assertFileDoesNotExist($this->keyFile);
    }
}
