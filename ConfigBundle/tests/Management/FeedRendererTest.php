<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\FeedProviderInterface;
use c975L\ConfigBundle\Management\FeedRenderer;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class FeedRendererTest extends TestCase
{
    private const array CONFIG = ['site-url' => 'https://example.com/', 'site-name' => 'Example', 'site-author' => null, 'seo-robots-private' => false];

    // The real template, so what is checked is the document a feed reader gets
    private function createRenderer(array $providers, array $config = []): FeedRenderer
    {
        $config = [...self::CONFIG, ...$config];
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): mixed => $config[$key] ?? null);

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LConfig');

        return new FeedRenderer($configService, new Environment($loader), new ArrayAdapter(), $providers);
    }

    private function createProvider(string $name, array $entries): FeedProviderInterface
    {
        $provider = $this->createStub(FeedProviderInterface::class);
        $provider->method('getFeedName')->willReturn($name);
        $provider->method('getFeedTitle')->willReturn('Latest ' . $name);
        $provider->method('getEntries')->willReturnCallback(static fn (int $limit): array => \array_slice($entries, 0, $limit));

        return $provider;
    }

    private function entry(string $slug, string $date, array $extra = []): array
    {
        return ['url' => 'https://example.com/' . $slug, 'title' => ucfirst($slug), 'updated' => new \DateTimeImmutable($date), ...$extra];
    }

    public function testTheFeedIsAValidAtomDocument(): void
    {
        $content = $this->createRenderer([$this->createProvider('strip', [
            $this->entry('second', '2026-10-08', ['summary' => '<p>Tom &amp; Jerry&nbsp;<b>fight</b></p>', 'image' => 'https://example.com/a.webp?x=1&y=2']),
            $this->entry('first', '2026-10-01'),
        ])])->render('strip', 'fr');

        $xml = simplexml_load_string((string) $content);
        $this->assertNotFalse($xml);

        $this->assertSame('Example - Latest strip', (string) $xml->title);
        $this->assertSame('https://example.com/feed/strip.xml', (string) $xml->id);
        $this->assertStringStartsWith('2026-10-08T00:00:00', (string) $xml->updated);
        $this->assertCount(2, $xml->entry);
        // Markup stripped, entities decoded and spaces collapsed, the XML escaping being the template's job
        $this->assertSame('Tom & Jerry fight', (string) $xml->entry[0]->summary);
        $this->assertStringContainsString('<img src="https://example.com/a.webp?x=1&amp;y=2"', (string) $xml->entry[0]->content);
        $this->assertCount(0, $xml->entry[1]->content);
    }

    public function testAnUnknownOrEmptyFeedRendersNothing(): void
    {
        $renderer = $this->createRenderer([$this->createProvider('book', [])]);

        $this->assertNull($renderer->render('book', 'en'));
        $this->assertNull($renderer->render('nothing', 'en'));
    }

    // A site kept out of search engines does not push its content out, and nothing can be linked before "site-url" is known
    public function testAClosedSiteHasNoFeed(): void
    {
        $providers = [$this->createProvider('strip', [$this->entry('one', '2026-10-01')])];

        foreach ([['seo-robots-private' => true], ['site-url' => '']] as $config) {
            $renderer = $this->createRenderer($providers, $config);
            $this->assertNull($renderer->render('strip', 'en'));
            $this->assertSame([], $renderer->available());
        }
    }

    public function testOnlyTheFeedsHoldingAnEntryAreAnnounced(): void
    {
        $renderer = $this->createRenderer([
            $this->createProvider('strip', [$this->entry('one', '2026-10-01')]),
            $this->createProvider('book', []),
        ]);

        $this->assertSame(['strip' => 'Latest strip'], $renderer->available());
    }

    public function testTwoProvidersCannotShareAName(): void
    {
        $this->expectException(\LogicException::class);

        $this->createRenderer([$this->createProvider('strip', []), $this->createProvider('strip', [])])->available();
    }

    public function testALongSummaryIsCutOnAWord(): void
    {
        $content = $this->createRenderer([$this->createProvider('strip', [
            $this->entry('one', '2026-10-01', ['summary' => str_repeat('word ', 100)]),
        ])])->render('strip', 'en');

        $summary = (string) simplexml_load_string((string) $content)->entry[0]->summary;
        $this->assertStringEndsWith('word…', $summary);
        $this->assertLessThanOrEqual(301, mb_strlen($summary));
    }
}
