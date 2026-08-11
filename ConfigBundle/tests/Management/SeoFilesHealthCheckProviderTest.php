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
use c975L\ConfigBundle\Management\SeoFilesHealthCheckProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SeoFilesClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SeoFilesHealthCheckProviderTest extends TestCase
{
    private const string VALID_SITEMAP = '<?xml version="1.0"?><urlset><url><loc>https://example.com/</loc></url></urlset>';
    private const string VALID_SITEMAP_INDEX = '<?xml version="1.0"?><sitemapindex><sitemap><loc>https://example.com/sitemap-page.xml</loc></sitemap><sitemap><loc>https://example.com/sitemap-book.xml</loc></sitemap></sitemapindex>';
    private const string EMPTY_SITEMAP = '<?xml version="1.0"?><urlset></urlset>';
    private const string EMPTY_SITEMAP_INDEX = '<?xml version="1.0"?><sitemapindex></sitemapindex>';
    private const string OPEN_ROBOTS = "User-agent: *\nDisallow:\n";
    private const string BLOCKING_ROBOTS = "User-agent: *\nDisallow: /\n";
    private const string PARTIAL_DISALLOW_ROBOTS = "User-agent: *\nDisallow: /admin/\n";
    private const string SCOPED_DISALLOW_ROBOTS = "User-agent: SomeBot\nDisallow: /\n\nUser-agent: *\nDisallow:\n";
    private const string HUMANS = "# TEAM\n\tAdministrator: Someone\n";
    private const string LLMS = "# Example\n\n## Site\n\n- [About](https://example.com/about): Who we are\n- [Contact](https://example.com/contact)\n";

    // A real resolver over a stubbed config, so the trailing-slash normalisation the provider relies on is exercised rather than stubbed away
    private function createSiteUrlResolver(?string $siteUrl): SiteUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new SiteUrlResolver($configService);
    }

    // humans.txt and llms.txt answer for every case a test doesn't declare them in, so each one only states the file it is about: a fine humans.txt and no llms.txt at all, which is the state of a site with nothing to index
    private function createClient(array $responses): SeoFilesClient
    {
        $declared = array_column($responses, 0);
        $defaults = [
            ['https://example.com/humans.txt', ['statusCode' => 200, 'content' => self::HUMANS]],
            ['https://example.com/llms.txt', ['statusCode' => 404, 'content' => '']],
        ];

        foreach ($defaults as $default) {
            if (!in_array($default[0], $declared, true)) {
                $responses[] = $default;
            }
        }

        $client = $this->createStub(SeoFilesClient::class);
        $client->method('fetch')->willReturnMap($responses);

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

    // Every test builds its provider here, so the "seo-robots-private" config it reads has a single place to be set
    private function createProvider(SiteUrlResolver $siteUrlResolver, SeoFilesClient $client, bool $isPrivate = false): SeoFilesHealthCheckProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key): mixed => 'seo-robots-private' === $key ? $isPrivate : null
        );

        return new SeoFilesHealthCheckProvider($siteUrlResolver, $client, $this->createTranslator(), $configService);
    }

    public function testGetKindReturnsSeoFiles(): void
    {
        $provider = $this->createProvider($this->createSiteUrlResolver(null), $this->createClient([]));

        $this->assertSame('seo-files', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = $this->createProvider($this->createSiteUrlResolver(null), $this->createClient([]));

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksReturnsOneRowEachForRobotsAndSitemapWhenBothAreFine(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);
        $results = $provider->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame('https://example.com/robots.txt', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[2]['status']);
        $this->assertSame('https://example.com/sitemap-site.xml', $results[2]['url']);
    }

    // A "site-url" saved with its trailing slash used to produce "https://example.com//robots.txt", which answers 404 and reports a perfectly deployed file as missing
    public function testRunChecksDoesNotDoubleTheSlashOfASiteUrlSavedWithOne(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com/'), $client);
        $results = $provider->runChecks();

        $this->assertSame('https://example.com/robots.txt', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenRobotsIsMissing(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 404, 'content' => '']],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenRobotsIsEmpty(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => '   ']],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWhenRobotsBlocksEverything(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::BLOCKING_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    // The same file, on a site that asked to stay out of search engines: what is the worst misconfiguration there is anywhere else is the state this one wanted, and a monthly warning about it would train its reader to ignore the row
    public function testRunChecksAcceptsABlockingRobotsOnAPrivateSite(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::BLOCKING_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client, true);

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    // Setting the config only reaches the deployed file on the next c975l:seo:files:create, and until then a site that believes itself private is still being crawled - the one thing worth a row here
    public function testRunChecksWarnsWhenAPrivateSiteStillServesAnOpenRobots(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client, true);
        $robots = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $robots['status']);
        $this->assertSame('label.health_check_robots_private_but_open', $robots['summary']);
    }

    public function testRunChecksDoesNotFlagAPartialDisallow(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::PARTIAL_DISALLOW_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksDoesNotFlagADisallowScopedToAnotherAgent(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::SCOPED_DISALLOW_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenSitemapIsMissing(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 404, 'content' => '']],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[2]['status']);
    }

    public function testRunChecksStatusIsErrorWhenSitemapIsNotValidXml(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => '<html>Not Found</html>']],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[2]['status']);
    }

    private function runSitemapCheck(string $content, ?\DateTimeImmutable $lastModified = null): array
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS, 'lastModified' => null]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => $content, 'lastModified' => $lastModified]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '', 'lastModified' => null]],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        return $provider->runChecks()[2];
    }

    // When the sitemap file itself was last rewritten, the only thing telling the freshness checks apart
    private function writtenDaysAgo(int $daysAgo): \DateTimeImmutable
    {
        return new \DateTimeImmutable('-' . $daysAgo . ' days');
    }

    // Well-formed XML declaring nothing at all - Search Console reports "0 page discovered" for it without a single error, which is exactly what c975l:sitemaps:create never running on a deployment leaves behind
    public function testRunChecksStatusIsWarningWhenTheSitemapDeclaresNoUrl(): void
    {
        $result = $this->runSitemapCheck(self::EMPTY_SITEMAP);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame('label.health_check_sitemap_empty', $result['summary']);
    }

    // Nothing regenerated the file in a month, on a site whose sitemaps are documented as rebuilt weekly
    public function testRunChecksStatusIsWarningWhenTheSitemapFileHasNotBeenRewrittenForLong(): void
    {
        $result = $this->runSitemapCheck(self::VALID_SITEMAP, $this->writtenDaysAgo(90));

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame('label.health_check_sitemap_stale', $result['summary']);
    }

    public function testRunChecksStatusIsOkWhenTheSitemapFileWasRewrittenRecently(): void
    {
        $result = $this->runSitemapCheck(self::VALID_SITEMAP, $this->writtenDaysAgo(2));

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame('label.health_check_sitemap_ok_urls', $result['summary']);
    }

    // A response carrying no Last-Modified header says nothing about the file's freshness - not the same as it being stale
    public function testRunChecksStatusIsOkWhenTheResponseCarriesNoLastModified(): void
    {
        $this->assertSame(HealthCheckResult::STATUS_OK, $this->runSitemapCheck(self::VALID_SITEMAP)['status']);
    }

    // The whole point of reading the file's own date: a site whose content is simply stable declares months-old <lastmod>s while the command keeps regenerating the file faithfully, and used to be called stale for it
    public function testRunChecksStatusIsOkWhenTheFileIsFreshButItsLastmodsAreOld(): void
    {
        $oldDate = $this->writtenDaysAgo(90)->format('Y-m-d');
        $content = '<?xml version="1.0"?><urlset><url><loc>https://example.com/</loc><lastmod>' . $oldDate . '</lastmod></url></urlset>';

        $this->assertSame(HealthCheckResult::STATUS_OK, $this->runSitemapCheck($content, $this->writtenDaysAgo(1))['status']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(SeoFilesClient::class);
        $client->method('fetch')->willThrowException(new \RuntimeException('Connection refused'));

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $results = $provider->runChecks();
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[2]['status']);
    }

    public function testRunChecksAddsAnIndexRowPlusOneRowPerChildSitemapWhenAllAreFine(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP_INDEX]],
            ['https://example.com/sitemap-page.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-book.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);
        $results = $provider->runChecks();

        $this->assertCount(6, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[3]['status']);
        $this->assertSame('https://example.com/sitemap-index.xml', $results[3]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[4]['status']);
        $this->assertSame('https://example.com/sitemap-page.xml', $results[4]['url']);
        $this->assertSame('sitemap-page.xml', $results[4]['label']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[5]['status']);
        $this->assertSame('https://example.com/sitemap-book.xml', $results[5]['url']);
        $this->assertSame('sitemap-book.xml', $results[5]['label']);
    }

    public function testRunChecksDoesNotAddAnyRowWhenSitemapIndexIsMissing(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertCount(3, $provider->runChecks());
    }

    public function testRunChecksStatusIsErrorWhenSitemapIndexIsNotValidXml(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => '<html>Not Found</html>']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);
        $results = $provider->runChecks();

        $this->assertCount(4, $results);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[3]['status']);
    }

    public function testRunChecksStatusIsWarningWhenSitemapIndexHasNoEntries(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::EMPTY_SITEMAP_INDEX]],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);
        $results = $provider->runChecks();

        $this->assertCount(4, $results);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[3]['status']);
    }

    public function testRunChecksOnlyFlagsTheOneChildSitemapThatIsUnreachable(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP_INDEX]],
            ['https://example.com/sitemap-page.xml', ['statusCode' => 404, 'content' => '']],
            ['https://example.com/sitemap-book.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);
        $results = $provider->runChecks();

        $this->assertCount(6, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[3]['status']);
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[4]['status']);
        $this->assertSame('https://example.com/sitemap-page.xml', $results[4]['url']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[5]['status']);
    }

    public function testRunChecksStatusIsErrorWhenAChildSitemapIsNotValidXml(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP_INDEX]],
            ['https://example.com/sitemap-page.xml', ['statusCode' => 200, 'content' => '<html>Not Found</html>']],
            ['https://example.com/sitemap-book.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);
        $results = $provider->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[4]['status']);
    }

    private function runHumansCheck(int $statusCode, string $content, ?\DateTimeImmutable $lastModified = null): array
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS, 'lastModified' => null]],
            ['https://example.com/humans.txt', ['statusCode' => $statusCode, 'content' => $content, 'lastModified' => $lastModified]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP, 'lastModified' => null]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '', 'lastModified' => null]],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        return $provider->runChecks()[1];
    }

    public function testRunChecksReportsHumansOnItsOwnRow(): void
    {
        $result = $this->runHumansCheck(200, self::HUMANS);

        $this->assertSame('https://example.com/humans.txt', $result['url']);
        $this->assertSame('humans.txt', $result['label']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    // SeoFilesWriter writes it for any site, so a missing one means c975l:seo:files:create never ran here - a warning, nothing being lost to a crawler meanwhile, unlike robots.txt
    public function testRunChecksStatusIsWarningWhenHumansIsMissing(): void
    {
        $result = $this->runHumansCheck(404, '');

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame('label.health_check_humans_missing', $result['summary']);
    }

    public function testRunChecksStatusIsWarningWhenHumansIsEmpty(): void
    {
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $this->runHumansCheck(200, '   ')['status']);
    }

    // Its "Last update" line is the date the file was written: a file nothing has rewritten for months states a date that has quietly started lying
    public function testRunChecksStatusIsWarningWhenHumansHasNotBeenRewrittenForLong(): void
    {
        $result = $this->runHumansCheck(200, self::HUMANS, $this->writtenDaysAgo(90));

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame('label.health_check_humans_stale', $result['summary']);
    }

    public function testRunChecksStatusIsOkWhenHumansWasRewrittenRecently(): void
    {
        $this->assertSame(HealthCheckResult::STATUS_OK, $this->runHumansCheck(200, self::HUMANS, $this->writtenDaysAgo(2))['status']);
    }

    // No llms.txt at all is a normal state - the writer writes none as long as no url declares a title and "seo-llms-summary" is empty - so it yields no row rather than a warning nobody can act on
    public function testRunChecksAddsNoLlmsRowWhenTheFileIsAbsent(): void
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        $this->assertSame([], array_filter($provider->runChecks(), static fn (array $row): bool => 'llms.txt' === $row['label']));
    }

    // A deployed one is reported on what it lists, which is what tells a real index from the bare title a misconfigured template would leave
    public function testRunChecksCountsTheEntriesOfADeployedLlms(): void
    {
        $result = $this->runLlmsCheck(self::LLMS);

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame('label.health_check_llms_ok', $result['summary']);
        $this->assertSame('llms.txt', $result['label']);
    }

    public function testRunChecksStatusIsWarningWhenLlmsListsNothing(): void
    {
        $result = $this->runLlmsCheck("# Example\n\n> A site about things.\n");

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame('label.health_check_llms_empty', $result['summary']);
    }

    private function runLlmsCheck(string $content): array
    {
        $client = $this->createClient([
            ['https://example.com/robots.txt', ['statusCode' => 200, 'content' => self::OPEN_ROBOTS]],
            ['https://example.com/llms.txt', ['statusCode' => 200, 'content' => $content]],
            ['https://example.com/sitemap-site.xml', ['statusCode' => 200, 'content' => self::VALID_SITEMAP]],
            ['https://example.com/sitemap-index.xml', ['statusCode' => 404, 'content' => '']],
        ]);

        $provider = $this->createProvider($this->createSiteUrlResolver('https://example.com'), $client);

        return $provider->runChecks()[2];
    }
}
