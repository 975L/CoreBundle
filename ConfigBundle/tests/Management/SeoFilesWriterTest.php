<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Management;

use c975L\ConfigBundle\Management\SeoFilesWriter;
use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class SeoFilesWriterTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-seo-files-writer-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (['/public', '/existingFiles/public', '/existingFiles'] as $folder) {
            array_map('unlink', glob($this->projectDir . $folder . '/*') ?: []);
            @rmdir($this->projectDir . $folder);
        }
        @rmdir($this->projectDir);
    }

    private function createConfigService(array $values = []): ConfigServiceInterface
    {
        $values += [
            'site-url' => 'https://example.com',
            'site-name' => 'Example',
            'site-author' => 'Laurent Marquet',
            'site-director' => null,
            'site-contact-email' => 'contact@example.com',
            'seo-robots-disallow' => [],
            'seo-robots-block-ai' => false,
            'seo-robots-ai-crawlers' => ['GPTBot', 'CCBot'],
            'seo-robots-extra' => null,
            'seo-humans-from' => 'France',
            'seo-humans-thanks' => null,
            'seo-llms-summary' => null,
        ];

        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(fn (string $key): mixed => $values[$key] ?? null);

        return $service;
    }

    // The real templates, rendered as the app renders them: what this class produces is the file's own text, which a stubbed renderer would say nothing about. Escaping off reproduces Symfony's default "name" strategy, which disables it for a .txt.twig - html entities in a robots.txt would be read as content
    private function createEnvironment(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../templates', 'c975LConfig');

        return new Environment($loader, ['autoescape' => false]);
    }

    /** @param SitemapProviderInterface[] $providers */
    private function createWriter(array $providers = [], array $configs = []): SeoFilesWriter
    {
        return new SeoFilesWriter($this->createConfigService($configs), $this->createEnvironment(), $providers, $this->projectDir, 'fr');
    }

    private function createProvider(string $name, array $urls): SitemapProviderInterface
    {
        $provider = $this->createStub(SitemapProviderInterface::class);
        $provider->method('getSitemapName')->willReturn($name);
        $provider->method('getUrls')->willReturn($urls);

        return $provider;
    }

    private function read(string $file): string
    {
        return (string) file_get_contents($this->projectDir . '/public/' . $file);
    }

    // robots.txt and humans.txt are written for any site, whatever it publishes
    public function testWriteCreatesRobotsAndHumans(): void
    {
        $files = $this->createWriter()->write();

        $this->assertSame(['robots.txt', 'humans.txt'], $files);
        $this->assertFileExists($this->projectDir . '/public/robots.txt');
        $this->assertFileExists($this->projectDir . '/public/humans.txt');
    }

    // The whole site stays crawlable by default, only the configured paths being forbidden
    public function testRobotsAllowsEverythingButTheConfiguredPaths(): void
    {
        $this->createWriter([], ['seo-robots-disallow' => ['/*.pdf$', '/private/']])->write();

        $robots = $this->read('robots.txt');
        $this->assertStringContainsString("User-agent: *\nAllow: /\n", $robots);
        $this->assertStringContainsString('Disallow: /*.pdf$', $robots);
        $this->assertStringContainsString('Disallow: /private/', $robots);
    }

    // Declaring a sitemap index that isn't deployed is a Search Console error, so the line waits for the file c975l:sitemaps:create writes
    public function testRobotsDeclaresTheSitemapIndexOnlyOnceItExists(): void
    {
        $this->createWriter()->write();
        $this->assertStringNotContainsString('Sitemap:', $this->read('robots.txt'));

        file_put_contents($this->projectDir . '/public/sitemap-index.xml', '<sitemapindex/>');
        $this->createWriter()->write();

        $this->assertStringContainsString('Sitemap: https://example.com/sitemap-index.xml', $this->read('robots.txt'));
    }

    // Blocking the crawlers that train models contradicts publishing an llms.txt for them to read, so it only happens when a site asks for it
    public function testRobotsBlocksAiCrawlersOnlyWhenAskedTo(): void
    {
        $this->createWriter()->write();
        $this->assertStringNotContainsString('GPTBot', $this->read('robots.txt'));

        $this->createWriter([], ['seo-robots-block-ai' => true])->write();

        $robots = $this->read('robots.txt');
        $this->assertStringContainsString("User-agent: GPTBot\nUser-agent: CCBot\nDisallow: /", $robots);
    }

    // Anything the settings don't cover, appended as typed
    public function testRobotsAppendsTheExtraLines(): void
    {
        $this->createWriter([], ['seo-robots-extra' => "User-agent: BadBot\nDisallow: /"])->write();

        $this->assertStringContainsString("User-agent: BadBot\nDisallow: /", $this->read('robots.txt'));
    }

    // The one date nobody has to remember to bump, which is the whole point of generating this file
    public function testHumansHoldsTheSiteIdentityAndTheDayItWasWritten(): void
    {
        $this->createWriter([], ['seo-humans-thanks' => "Symfony\nTwig"])->write();

        $humans = $this->read('humans.txt');
        $this->assertStringContainsString('Administrator: Laurent Marquet', $humans);
        $this->assertStringContainsString('Contact: contact@example.com', $humans);
        $this->assertStringContainsString('From: France', $humans);
        $this->assertStringContainsString("\tSymfony\n\tTwig", $humans);
        $this->assertStringContainsString('Last update: ' . date('d/m/Y'), $humans);
        $this->assertStringContainsString('Language: French', $humans);
    }

    // "site-author" is the credit line and "site-director" the legally required one: a site filling only the second must still name someone
    public function testHumansFallsBackOnThePublicationDirector(): void
    {
        $this->createWriter([], ['site-author' => null, 'site-director' => 'Jane Doe'])->write();

        $this->assertStringContainsString('Administrator: Jane Doe', $this->read('humans.txt'));
    }

    // A line whose config is empty is dropped rather than written with nothing after the colon
    public function testHumansDropsTheLinesWithNoValue(): void
    {
        $this->createWriter([], ['seo-humans-from' => ' '])->write();

        $this->assertStringNotContainsString('From:', $this->read('humans.txt'));
    }

    // One section per bundle, from the urls it already declares for its sitemap
    public function testLlmsListsOneSectionPerProvider(): void
    {
        $files = $this->createWriter([
            $this->createProvider('site', [['loc' => 'https://example.com/about', 'title' => 'About', 'description' => 'Who we are']]),
            $this->createProvider('book', [['loc' => 'https://example.com/book/1', 'title' => 'Tome 1']]),
        ], ['seo-llms-summary' => 'A site about things.'])->write();

        $this->assertContains('llms.txt', $files);
        $llms = $this->read('llms.txt');
        $this->assertStringContainsString("# Example\n", $llms);
        $this->assertStringContainsString('> A site about things.', $llms);
        $this->assertStringContainsString("## Site\n", $llms);
        $this->assertStringContainsString('- [About](https://example.com/about): Who we are', $llms);
        $this->assertStringContainsString("## Book\n", $llms);
        $this->assertStringContainsString("- [Tome 1](https://example.com/book/1)\n", $llms);
    }

    // llms.txt is a curated index, not the sitemap in Markdown: an url with no title is what a bundle leaves out of it
    public function testLlmsSkipsUrlsWithNoTitle(): void
    {
        $this->createWriter([
            $this->createProvider('site', [
                ['loc' => 'https://example.com/about', 'title' => 'About'],
                ['loc' => 'https://example.com/legal'],
            ]),
        ])->write();

        $llms = $this->read('llms.txt');
        $this->assertStringContainsString('/about', $llms);
        $this->assertStringNotContainsString('/legal', $llms);
    }

    // A provider whose urls carry no title contributes no section at all, rather than an empty heading
    public function testLlmsSkipsProvidersWithNoTitledUrl(): void
    {
        $this->createWriter(
            [$this->createProvider('gallery', [['loc' => 'https://example.com/photo/1']])],
            ['seo-llms-summary' => 'A site about things.']
        )->write();

        $this->assertStringNotContainsString('## Gallery', $this->read('llms.txt'));
    }

    // A page's own summary is written for a meta description: it may hold markup and newlines, where one link is one line here
    public function testLlmsFlattensAndTruncatesTheDescription(): void
    {
        $this->createWriter([
            $this->createProvider('site', [[
                'loc' => 'https://example.com/about',
                'title' => 'About',
                'description' => "<p>Who\nwe are, " . str_repeat('at length ', 30) . '</p>',
            ]]),
        ])->write();

        $line = strtok($this->read('llms.txt'), "\n");
        while (false !== $line && !str_starts_with($line, '- [About]')) {
            $line = strtok("\n");
        }

        $this->assertIsString($line);
        $this->assertStringContainsString('Who we are, at length', $line);
        $this->assertStringEndsWith('…', $line);
        $this->assertLessThan(260, mb_strlen($line));
    }

    // Nothing to index and nothing configured to say: an llms.txt holding a bare title helps no one, and whatever a previous run left must stop being served
    public function testLlmsIsNotWrittenWithNothingToSay(): void
    {
        $stale = $this->projectDir . '/public/llms.txt';
        file_put_contents($stale, "# Example\n<!-- c975l:seo:files:create -->");

        $files = $this->createWriter([$this->createProvider('site', [['loc' => 'https://example.com/about']])])->write();

        $this->assertNotContains('llms.txt', $files);
        $this->assertFileDoesNotExist($stale);
    }

    // Nothing to say is the default state of a site that has not filled the summary yet: a hand-written llms.txt must be saved rather than removed on the first run, like the two files that are always written
    public function testAHandwrittenLlmsIsBackedUpBeforeBeingRemoved(): void
    {
        file_put_contents($this->projectDir . '/public/llms.txt', '# Example');

        $files = $this->createWriter([$this->createProvider('site', [['loc' => 'https://example.com/about']])])->write();

        $this->assertNotContains('llms.txt', $files);
        $this->assertFileDoesNotExist($this->projectDir . '/public/llms.txt');
        $this->assertSame('# Example', file_get_contents($this->projectDir . '/existingFiles/public/llms.txt.old'));
    }

    // A site that hand-wrote its robots.txt years ago must not lose it to the first run
    public function testAHandwrittenFileIsBackedUpBeforeBeingReplaced(): void
    {
        file_put_contents($this->projectDir . '/public/robots.txt', "User-agent: *\nDisallow: /admin/");

        $this->createWriter()->write();

        $this->assertStringContainsString(SeoFilesWriter::GENERATED_MARKER, $this->read('robots.txt'));
        $this->assertSame("User-agent: *\nDisallow: /admin/", file_get_contents($this->projectDir . '/existingFiles/public/robots.txt.old'));
    }

    // A file this class wrote is reproducible from the configs, so refreshing it needs no backup - and a backup folder filling up with copies of itself on every run
    public function testAGeneratedFileIsRefreshedWithoutBeingBackedUp(): void
    {
        $this->createWriter()->write();
        $this->createWriter([], ['seo-robots-disallow' => ['/private/']])->write();

        $this->assertStringContainsString('Disallow: /private/', $this->read('robots.txt'));
        $this->assertFileDoesNotExist($this->projectDir . '/existingFiles/public/robots.txt.old');
    }

    // A json config is free-form until an admin saves nonsense in it, and what lands here is parsed by crawlers
    public function testMalformedListsAreDroppedRatherThanWritten(): void
    {
        $this->createWriter([], ['seo-robots-disallow' => ['/private/', '', ['nested'], 42]])->write();

        $robots = $this->read('robots.txt');
        $this->assertStringContainsString('Disallow: /private/', $robots);
        $this->assertSame(1, substr_count($robots, 'Disallow:'));
    }
}
