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
use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Management\SitemapRobotsHealthCheckProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SeoFilesClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SitemapRobotsHealthCheckProviderTest extends TestCase
{
    private const ROBOTS_URL = 'https://example.com/robots.txt';
    private const OPEN_ROBOTS = "User-agent: *\nAllow: /\n";

    // A real resolver over a stubbed config, so the trailing-slash normalisation the provider relies on is exercised rather than stubbed away
    private function createSiteUrlResolver(?string $siteUrl): SiteUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new SiteUrlResolver($configService);
    }

    private function createSeoFilesClient(array $response): SeoFilesClient
    {
        $client = $this->createStub(SeoFilesClient::class);
        $client->method('fetch')->willReturn($response + ['statusCode' => 200, 'content' => '', 'lastModified' => null]);

        return $client;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    // @param list<string> $urls
    private function createSitemapProvider(string $name, array $urls): SitemapProviderInterface
    {
        $provider = $this->createStub(SitemapProviderInterface::class);
        $provider->method('getSitemapName')->willReturn($name);
        $provider->method('getUrls')->willReturn(array_map(static fn (string $url) => ['loc' => $url], $urls));

        return $provider;
    }

    private function createProvider(?string $siteUrl, array $robots, array $sitemapProviders = []): SitemapRobotsHealthCheckProvider
    {
        return new SitemapRobotsHealthCheckProvider(
            $this->createSiteUrlResolver($siteUrl),
            $this->createSeoFilesClient($robots),
            $this->createTranslator(),
            $sitemapProviders,
        );
    }

    public function testGetKindReturnsSitemapRobots(): void
    {
        $this->assertSame('sitemap-robots', $this->createProvider(null, [])->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $this->assertSame([], $this->createProvider(null, ['content' => self::OPEN_ROBOTS])->runChecks());
    }

    // A missing robots.txt is already the error SeoFilesHealthCheckProvider raises - repeating it would leave two rows for one defect
    public function testRunChecksReturnsNoRowWhenRobotsIsMissing(): void
    {
        $provider = $this->createProvider('https://example.com', ['statusCode' => 404, 'content' => '']);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksReturnsNoRowWhenRobotsIsEmpty(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "  \n "]);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(SeoFilesClient::class);
        $client->method('fetch')->willThrowException(new \RuntimeException('Connection refused'));

        $provider = new SitemapRobotsHealthCheckProvider(
            $this->createSiteUrlResolver('https://example.com'),
            $client,
            $this->createTranslator(),
            [],
        );
        $results = $provider->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame('Connection refused', $results[0]['details']['error']);
    }

    // The summary row is there whether or not anything is blocked: a check whose light goes out when it passes is indistinguishable from one that never ran
    public function testRunChecksReturnsTheSummaryRowWhenNothingIsBlocked(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => self::OPEN_ROBOTS], [
            $this->createSitemapProvider('site', ['https://example.com/', 'https://example.com/pages/contact']),
        ]);
        $results = $provider->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame(self::ROBOTS_URL, $results[0]['url']);
        $this->assertSame(['checked' => 2, 'blocked' => 0], $results[0]['details']);
    }

    // The contradiction this check exists for: an url the sitemap hands to search engines and the robots.txt refuses them
    public function testRunChecksReportsAnUrlItsOwnRobotsBlocks(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "User-agent: *\nDisallow: /pages/\n"], [
            $this->createSitemapProvider('site', ['https://example.com/', 'https://example.com/pages/contact']),
        ]);
        $results = $provider->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame(['checked' => 2, 'blocked' => 1], $results[0]['details']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[1]['status']);
        $this->assertSame('https://example.com/pages/contact', $results[1]['url']);
        $this->assertSame('pages/contact', $results[1]['label']);
    }

    // A file scoping its rules to Googlebot blocks the declared url just as effectively, and reading only the wildcard group would call it green
    public function testRunChecksReadsRulesScopedToGooglebot(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "User-agent: Googlebot\nDisallow: /private/\n"], [
            $this->createSitemapProvider('site', ['https://example.com/', 'https://example.com/private/x']),
        ]);
        $results = $provider->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame(['checked' => 2, 'blocked' => 1], $results[0]['details']);
        $this->assertSame('https://example.com/private/x', $results[1]['url']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[1]['status']);
    }

    // Every provider contributes its urls, which is what makes a shop's products checked alongside a site's pages
    public function testRunChecksCrossChecksEverySitemapProvider(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "User-agent: *\nDisallow: /shop/\n"], [
            $this->createSitemapProvider('site', ['https://example.com/pages/contact']),
            $this->createSitemapProvider('shop', ['https://example.com/shop/products/book', 'https://example.com/shop']),
        ]);
        $results = $provider->runChecks();

        $this->assertSame(['checked' => 3, 'blocked' => 1], $results[0]['details']);
        $this->assertSame('https://example.com/shop/products/book', $results[1]['url']);
    }

    // An url on another host isn't governed by this robots.txt, and reporting a rule that doesn't apply to it would be a false positive
    public function testRunChecksIgnoresUrlsOnAnotherHost(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "User-agent: *\nDisallow: /assets/\n"], [
            $this->createSitemapProvider('site', ['https://cdn.example.net/assets/photo.jpg', 'https://example.com/']),
        ]);
        $results = $provider->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(['checked' => 1, 'blocked' => 0], $results[0]['details']);
    }

    // Taken as mixed the way DeclaredUrlsHealthCheckProvider does: the implementations are other bundles' code, and one incomplete row is skipped rather than taking the whole check down
    public function testRunChecksSkipsAnUrlWithoutALocation(): void
    {
        $sitemapProvider = $this->createStub(SitemapProviderInterface::class);
        $sitemapProvider->method('getSitemapName')->willReturn('site');
        $sitemapProvider->method('getUrls')->willReturn([['lastmod' => '2026-08-08'], ['loc' => ''], ['loc' => 'https://example.com/']]);

        $provider = $this->createProvider('https://example.com', ['content' => self::OPEN_ROBOTS], [$sitemapProvider]);

        $this->assertSame(['checked' => 1, 'blocked' => 0], $provider->runChecks()[0]['details']);
    }

    // robots.txt matches its rules against the path and its query string, never the host
    public function testRunChecksMatchesTheQueryString(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "User-agent: *\nDisallow: /*?print\n"], [
            $this->createSitemapProvider('site', ['https://example.com/pages/contact?print=1']),
        ]);
        $results = $provider->runChecks();

        $this->assertSame(['checked' => 1, 'blocked' => 1], $results[0]['details']);
        $this->assertSame('/pages/contact?print=1', $results[1]['details']['path']);
    }

    // The site root has no path of its own, and a rule blocking "/" blocks it
    public function testRunChecksReportsTheSiteRootAsBlockedByABlanketDisallow(): void
    {
        $provider = $this->createProvider('https://example.com', ['content' => "User-agent: *\nDisallow: /\n"], [
            $this->createSitemapProvider('site', ['https://example.com']),
        ]);
        $results = $provider->runChecks();

        $this->assertSame(['checked' => 1, 'blocked' => 1], $results[0]['details']);
        $this->assertSame('/', $results[1]['details']['path']);
    }

    // A site declared with an uppercase host is the same site, and its urls must not be dropped as foreign
    public function testRunChecksComparesHostsCaseInsensitively(): void
    {
        $provider = $this->createProvider('https://Example.com', ['content' => self::OPEN_ROBOTS], [
            $this->createSitemapProvider('site', ['https://example.com/pages/contact']),
        ]);

        $this->assertSame(['checked' => 1, 'blocked' => 0], $provider->runChecks()[0]['details']);
    }
}
