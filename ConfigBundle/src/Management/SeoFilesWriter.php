<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

// Writes public/robots.txt, public/humans.txt and public/llms.txt from the "seo" configs and the urls the sitemap providers already declare - the same "generated static file under public/" treatment as SitemapWriter, and for the same reason: served by the web server, they keep answering 200 during a maintenance, where a controller-rendered robots.txt would 503 and stop the crawl of the whole site. Lives here rather than in SiteBundle so any combination of bundles gets its SEO files, SiteBundle installed or not
class SeoFilesWriter
{
    // Carried by every file this class writes, and how a file it wrote is told apart from one the site hand-wrote before this existed - the latter is backed up rather than silently overwritten (see backupIfHandwritten())
    public const GENERATED_MARKER = 'c975l:seo:files:create';

    // Long enough to say what a page is, short enough to keep llms.txt readable when a site declares hundreds of them
    private const DESCRIPTION_MAX_LENGTH = 200;

    private string $publicFolder;

    private string $backupFolder;

    private Filesystem $filesystem;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $environment,
        // Every SitemapProviderInterface implementation, whatever the bundle it comes from - the same iterator SitemapWriter gets, llms.txt being built from urls that are already declared rather than from a second contract each bundle would have to implement
        private readonly iterable $sitemapProviders,
        #[Autowire('%kernel.project_dir%')]
        string $projectDir,
        #[Autowire('%kernel.default_locale%')]
        private readonly string $defaultLocale,
    ) {
        $this->publicFolder = $projectDir . '/public';
        $this->backupFolder = $projectDir . '/existingFiles/public';
        $this->filesystem = new Filesystem();
    }

    // Writes every SEO file and returns the names written, so the command and the dashboard shortcut report what landed
    public function write(): array
    {
        $written = [
            $this->dump('robots.txt', $this->robotsContext()),
            $this->dump('humans.txt', $this->humansContext()),
        ];

        // Nothing to index and nothing to say about the site: an llms.txt holding a bare title helps no one, and the same "no content, no file" rule as an empty sitemap applies - any file left by a previous run is removed so nothing stale keeps being served, a hand-written one being backed up first like the two files above
        $llmsContext = $this->llmsContext();
        if (null === $llmsContext) {
            $llmsTarget = $this->publicFolder . '/llms.txt';
            $this->backupIfHandwritten($llmsTarget);
            $this->filesystem->remove($llmsTarget);
        } else {
            $written[] = $this->dump('llms.txt', $llmsContext);
        }

        return $written;
    }

    private function dump(string $file, array $context): string
    {
        $target = $this->publicFolder . '/' . $file;
        $this->backupIfHandwritten($target);
        $this->filesystem->dumpFile($target, $this->environment->render('@c975LConfig/seo/' . $file . '.twig', $context));

        return $file;
    }

    // A site that hand-wrote its robots.txt years ago must not lose it to the first run of this command: a file already carrying the marker is one this class wrote and is refreshed in place, anything else is moved to existingFiles/ (the same place ScaffoldInstaller puts what it replaces) and named in git-ignored territory, so the content is still there to copy back into the configs
    private function backupIfHandwritten(string $target): void
    {
        if (!is_file($target) || str_contains((string) file_get_contents($target), self::GENERATED_MARKER)) {
            return;
        }

        $this->filesystem->mkdir($this->backupFolder);
        $this->filesystem->rename($target, $this->backupFolder . '/' . basename($target) . '.old', true);
    }

    private function robotsContext(): array
    {
        return [
            'disallow' => $this->stringList($this->configService->get('seo-robots-disallow')),
            'blockAi' => (bool) $this->configService->get('seo-robots-block-ai'),
            'aiCrawlers' => $this->stringList($this->configService->get('seo-robots-ai-crawlers')),
            'extra' => $this->text($this->configService->get('seo-robots-extra')),
            'sitemap' => $this->sitemapIndexUrl(),
        ];
    }

    private function humansContext(): array
    {
        return [
            'siteName' => $this->text($this->configService->get('site-name')),
            // The person answering for the site, whoever the site chose to name: "site-author" is the credit line, "site-director" the legally required publication director, and a site rarely fills both
            'administrator' => $this->text($this->configService->get('site-author')) ?? $this->text($this->configService->get('site-director')),
            'contact' => $this->text($this->configService->get('site-contact-email')),
            'from' => $this->text($this->configService->get('seo-humans-from')),
            'thanks' => $this->text($this->configService->get('seo-humans-thanks')),
            'language' => $this->languageName(),
            // The date the file was written, which is the only "last update" nobody has to remember to bump - a hand-maintained humans.txt states a date that stops being true the day after it was typed
            'lastUpdate' => date('d/m/Y'),
        ];
    }

    // @return ?array null when there is neither a summary nor a single section to declare
    private function llmsContext(): ?array
    {
        $sections = $this->llmsSections();
        $summary = $this->text($this->configService->get('seo-llms-summary'));

        if ([] === $sections && null === $summary) {
            return null;
        }

        return [
            'siteName' => $this->text($this->configService->get('site-name')) ?? $this->text($this->configService->get('site-url')) ?? 'Site',
            'summary' => $summary,
            'sections' => $sections,
        ];
    }

    // One section per sitemap provider, built from the optional 'title'/'description' its urls carry (see SitemapProviderInterface). A provider declaring no title at all contributes nothing: llms.txt is meant to be a curated index, and a bare list of urls is what the sitemap already is
    private function llmsSections(): array
    {
        $sections = [];
        foreach ($this->sitemapProviders as $provider) {
            /** @var SitemapProviderInterface $provider */
            $links = [];
            foreach ($provider->getUrls() as $url) {
                $link = $this->link($url);
                if (null !== $link) {
                    $links[] = $link;
                }
            }

            if ([] !== $links) {
                $sections[] = ['title' => $this->sectionTitle($provider->getSitemapName()), 'links' => $links];
            }
        }

        return $sections;
    }

    // One line of llms.txt, or null for an url that has no business there. Taken as mixed on purpose, like DeclaredUrlsHealthCheckProvider's own reader: SitemapProviderInterface declares the shape, but the implementations are other bundles' code, and one incomplete row has to be skipped rather than take the whole file down
    private function link(mixed $url): ?array
    {
        if (!is_array($url)) {
            return null;
        }

        $title = $this->text($url['title'] ?? null);
        $location = $this->text($url['loc'] ?? null);

        if (null === $title || null === $location) {
            return null;
        }

        return ['title' => $title, 'url' => $location, 'description' => $this->description($url['description'] ?? null)];
    }

    // Heading of a bundle's own section, from the sitemap name it already declares ('book' gives "Book"), left untranslated like the health check kinds built the same way: a site wanting its own wording overrides @c975LConfig/seo/llms.txt.twig, rather than every bundle having to declare a label in a catalogue it doesn't own
    private function sectionTitle(string $name): string
    {
        return ucfirst(str_replace('-', ' ', $name));
    }

    // Only declared when the index is really deployed: a "Sitemap:" line pointing at a 404 is a Search Console error, and c975l:sitemaps:create writes no index at all until a provider has urls to declare
    private function sitemapIndexUrl(): ?string
    {
        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');

        return '' !== $siteUrl && is_file($this->publicFolder . '/sitemap-index.xml')
            ? $siteUrl . '/sitemap-index.xml'
            : null;
    }

    // Language of the site as a name rather than the "fr" the kernel holds, guarded since ext-intl is not required by this bundle
    private function languageName(): string
    {
        return class_exists(\Locale::class)
            ? \Locale::getDisplayLanguage($this->defaultLocale, 'en')
            : $this->defaultLocale;
    }

    // Kept to a single line and bounded: what a provider hands over is a page's own summary, written for a meta description and free to hold newlines, where one link is one line in llms.txt
    private function description(mixed $value): ?string
    {
        $description = $this->text(is_string($value) ? preg_replace('/\s+/', ' ', strip_tags($value)) : null);
        if (null === $description || mb_strlen($description) <= self::DESCRIPTION_MAX_LENGTH) {
            return $description;
        }

        return rtrim(mb_substr($description, 0, self::DESCRIPTION_MAX_LENGTH), " \t\n\r,;:.") . '…';
    }

    // Null rather than an empty string, so a template tells "not configured" (line dropped) from a value that is really there
    private function text(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return '' === $value ? null : $value;
    }

    // A json config is free-form until an admin saves nonsense in it: anything but a non-empty string is dropped rather than written into a file a crawler parses
    private function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(fn (mixed $item): ?string => $this->text($item), $value)));
    }
}
