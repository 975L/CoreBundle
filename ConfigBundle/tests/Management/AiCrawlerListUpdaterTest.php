<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Management\AiCrawlerListUpdater;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\AiCrawlerListClient;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AiCrawlerListUpdaterTest extends TestCase
{
    private const SOURCE = 'https://example.com/robots.json';

    private function createConfigService(array $current, ?string $source = self::SOURCE): ConfigServiceInterface
    {
        $values = ['seo-robots-ai-crawlers' => $current, 'seo-robots-ai-crawlers-source' => $source];

        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(static fn (string $key): mixed => $values[$key] ?? null);

        return $service;
    }

    private function createClient(array $upstream): AiCrawlerListClient
    {
        $client = $this->createStub(AiCrawlerListClient::class);
        $client->method('fetch')->willReturn($upstream);

        return $client;
    }

    private function createUpdater(
        array $current,
        array $upstream,
        ?string $source = self::SOURCE,
        ?ConfigRepository $configRepository = null,
        ?EntityManagerInterface $entityManager = null,
        ?ConfigServiceInterface $configService = null,
    ): AiCrawlerListUpdater {
        return new AiCrawlerListUpdater(
            $configService ?? $this->createConfigService($current, $source),
            $this->createClient($upstream),
            $configRepository ?? $this->createStub(ConfigRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class)
        );
    }

    private function createRepository(?Config $config): ConfigRepository
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findOneBySlug')->willReturn($config);

        return $repository;
    }

    public function testCompareReportsWhatAppearedUpstream(): void
    {
        $updater = $this->createUpdater(['GPTBot'], ['GPTBot', 'NewScraperBot', 'OtherBot']);

        $comparison = $updater->compare();

        $this->assertSame(['NewScraperBot', 'OtherBot'], $comparison['missing']);
        $this->assertSame(self::SOURCE, $comparison['source']);
    }

    // The whole point of not importing the upstream list wholesale: these fetch a page to answer someone's question and cite it back, so blocking them costs visibility and gains nothing
    public function testCompareLeavesTheAnswerEnginesOutAndReportsThem(): void
    {
        $updater = $this->createUpdater(['GPTBot'], ['Claude-User', 'PerplexityBot', 'NewScraperBot']);

        $comparison = $updater->compare();

        $this->assertSame(['NewScraperBot'], $comparison['missing']);
        $this->assertSame(['Claude-User', 'PerplexityBot'], $comparison['answerEngines']);
    }

    // A site that decided to block one anyway keeps it: it's already in its list, so there is nothing to report about it
    public function testCompareSaysNothingAboutAnAnswerEngineTheSiteAlreadyBlocks(): void
    {
        $updater = $this->createUpdater(['PerplexityBot'], ['PerplexityBot']);

        $comparison = $updater->compare();

        $this->assertSame([], $comparison['missing']);
        $this->assertSame([], $comparison['answerEngines']);
    }

    // User agent tokens are matched case-insensitively by the crawlers themselves, so a list written in another case must not grow a duplicate
    public function testCompareMatchesUserAgentsRegardlessOfCase(): void
    {
        $updater = $this->createUpdater(['gptbot'], ['GPTBot']);

        $this->assertSame([], $updater->compare()['missing']);
    }

    // "none" is how a site keeps its list by hand: nothing is fetched, and nothing is ever reported
    // An empty source says the same, but only until the next c975l:config:load-all writes the declared url back into the row - which is why the word exists at all, and why it is checked whatever its case
    #[DataProvider('provideSourcesNamingNothing')]
    public function testCompareFetchesNothingWithoutASource(string $source): void
    {
        $client = $this->createMock(AiCrawlerListClient::class);
        $client->expects($this->never())->method('fetch');

        $updater = new AiCrawlerListUpdater(
            $this->createConfigService(['GPTBot'], $source),
            $client,
            $this->createStub(ConfigRepository::class),
            $this->createStub(EntityManagerInterface::class)
        );

        $this->assertSame(['missing' => [], 'answerEngines' => [], 'source' => null], $updater->compare());
    }

    public static function provideSourcesNamingNothing(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace' => ['   '];
        yield 'none' => [AiCrawlerListUpdater::NO_SOURCE];
        yield 'none, as typed' => ['  None  '];
    }

    // The config is free-form until an admin saves something else in it, and a list that stopped being one must not take the check down
    public function testCompareTreatsAMalformedConfigAsAnEmptyList(): void
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(static fn (string $key): mixed => 'seo-robots-ai-crawlers-source' === $key ? self::SOURCE : 'not-a-list');

        $updater = $this->createUpdater([], ['GPTBot'], configService: $service);

        $this->assertSame(['GPTBot'], $updater->compare()['missing']);
    }

    public function testApplyAddsTheGivenAgentsToTheConfigSorted(): void
    {
        $config = (new Config())->setSlug('seo-robots-ai-crawlers')->setValue('["GPTBot"]');
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($config);
        $entityManager->expects($this->once())->method('flush');

        $updater = $this->createUpdater(['GPTBot'], [], configRepository: $this->createRepository($config), entityManager: $entityManager);

        $updated = $updater->apply(['NewScraperBot', 'AnotherBot']);

        $this->assertSame(['AnotherBot', 'GPTBot', 'NewScraperBot'], $updated);
        $this->assertSame('["AnotherBot","GPTBot","NewScraperBot"]', $config->getValue());
    }

    // Additive on purpose: a name this site added by hand, or one upstream has since dropped, is never removed by an update it didn't ask for
    public function testApplyNeverRemovesWhatTheSiteAlreadyHad(): void
    {
        $config = (new Config())->setSlug('seo-robots-ai-crawlers')->setValue('["SiteOwnBot"]');

        $updater = $this->createUpdater(['SiteOwnBot'], [], configRepository: $this->createRepository($config));

        $this->assertSame(['NewBot', 'SiteOwnBot'], $updater->apply(['NewBot']));
    }

    // robots.txt is built from the cached configs, so a list updated behind the cache would only take effect on the next clear
    public function testApplyInvalidatesTheConfigCache(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->method('get')->willReturn([]);
        $configService->expects($this->once())->method('invalidateCache');

        $updater = $this->createUpdater([], [], configRepository: $this->createRepository((new Config())->setSlug('seo-robots-ai-crawlers')), configService: $configService);

        $updater->apply(['NewBot']);
    }

    // A site whose configs were never loaded must be told to load them, not have the update silently do nothing
    public function testApplyThrowsWhenTheConfigIsMissing(): void
    {
        $updater = $this->createUpdater([], [], configRepository: $this->createRepository(null));

        $this->expectException(\RuntimeException::class);

        $updater->apply(['NewBot']);
    }
}
