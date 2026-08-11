<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Attribute\AsHealthCheck;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\AiCrawlerListUpdater;
use c975L\ConfigBundle\Management\AiCrawlersHealthCheckProvider;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class AiCrawlersHealthCheckProviderTest extends TestCase
{
    private const string SOURCE = 'https://example.com/robots.json';

    private function createConfigService(bool $blocksAi): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(static fn (string $key): mixed => 'seo-robots-block-ai' === $key ? $blocksAi : null);

        return $service;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }

    private function createProvider(bool $blocksAi, array $comparison = [], ?\Throwable $failure = null): AiCrawlersHealthCheckProvider
    {
        $updater = $this->createStub(AiCrawlerListUpdater::class);
        if (null !== $failure) {
            $updater->method('compare')->willThrowException($failure);
        } else {
            $updater->method('compare')->willReturn($comparison + ['missing' => [], 'answerEngines' => [], 'source' => self::SOURCE]);
        }

        return new AiCrawlersHealthCheckProvider($this->createConfigService($blocksAi), $updater, $this->createTranslator());
    }

    public function testGetKindReturnsAiCrawlers(): void
    {
        $this->assertSame('ai-crawlers', $this->createProvider(true)->getKind());
    }

    // That list gains a handful of names every few months, where a weekly call to a third party for it would be noise
    public function testProviderRunsMonthly(): void
    {
        $attributes = new \ReflectionClass(AiCrawlersHealthCheckProvider::class)->getAttributes(AsHealthCheck::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(AsHealthCheck::FREQUENCY_MONTHLY, $attributes[0]->newInstance()->frequency);
    }

    // A site that doesn't block them has no use for the list, and nothing to be told about it - no row, and no call to the third party either
    public function testRunChecksReturnsNothingWhenTheSiteDoesNotBlockAiCrawlers(): void
    {
        $updater = $this->createMock(AiCrawlerListUpdater::class);
        $updater->expects($this->never())->method('compare');

        $provider = new AiCrawlersHealthCheckProvider($this->createConfigService(false), $updater, $this->createTranslator());

        $this->assertSame([], $provider->runChecks());
    }

    // An empty source is a deliberate opt-out, not something to report every month
    public function testRunChecksReturnsNothingWithoutASource(): void
    {
        $provider = $this->createProvider(true, ['source' => null]);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksStatusIsOkWhenNothingAppearedUpstream(): void
    {
        $results = $this->createProvider(true)->runChecks();

        $this->assertCount(1, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame('label.health_check_ai_crawlers_ok', $results[0]['summary']);
        $this->assertSame('seo-robots-ai-crawlers', $results[0]['label']);
    }

    // The names are kept in the row's details, so the dashboard says what would be blocked without having to run the command
    public function testRunChecksStatusIsWarningWhenCrawlersAppearedUpstream(): void
    {
        $results = $this->createProvider(true, ['missing' => ['NewScraperBot', 'OtherBot'], 'answerEngines' => ['Claude-User']])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
        $this->assertSame('label.health_check_ai_crawlers_outdated', $results[0]['summary']);
        $this->assertSame(['NewScraperBot', 'OtherBot'], $results[0]['details']['missing']);
        $this->assertSame(['Claude-User'], $results[0]['details']['answerEngines']);
    }

    // A third party is called here: an unreachable list must be a row saying so, never an exception taking the whole health check run down
    public function testRunChecksReturnsAnErrorRowWhenTheListCannotBeRead(): void
    {
        $results = $this->createProvider(true, failure: new \RuntimeException('Connection refused'))->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $results[0]['status']);
        $this->assertSame('Connection refused', $results[0]['details']['error']);
    }
}
