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
    private const int DESCRIPTION_MAX_LENGTH = 200;

    private readonly string $publicFolder;

    private readonly string $backupFolder;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $environment,
        // Every SitemapProviderInterface implementation, whatever the bundle it comes from - the same iterator SitemapWriter gets, llms.txt being built from urls that are already declared rather than from a second contract each bundle would have to implement
        private readonly iterable $sitemapProviders,
        #[Autowire(param: 'kernel.project_dir')]
        string $projectDir,
        #[Autowire(param: 'kernel.default_locale')]
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
        $aiCrawlers = $this->stringList($this->configService->get('seo-robots-ai-crawlers'));

        return [
            // Everything below describes a site meant to be indexed, and the template drops the lot rather than mixing a "Disallow: /" into it: a robots.txt holding both that and an "Allow: /" leaves the site open, RFC 9309 settling a tie between two rules of equal length in favour of the least restrictive one
            'private' => $this->isPrivate(),
            'disallow' => $this->stringList($this->configService->get('seo-robots-disallow')),
            'blockAi' => (bool) $this->configService->get('seo-robots-block-ai'),
            'aiCrawlers' => $aiCrawlers,
            'answerEngines' => $this->answerEnginesLeftAllowed($aiCrawlers),
            'extra' => $this->text($this->configService->get('seo-robots-extra')),
            'sitemap' => $this->sitemapIndexUrl(),
        ];
    }

    // The answer engines the file really lets through, named in it so a robots.txt says what it allows and not only what it blocks. Read from AiCrawlerListUpdater's own list, which is what keeps them out of the blocked one, minus any name a site added there by hand - claiming to allow a crawler the very same file blocks would be a lie, and adding one is explicitly a site's right
    private function answerEnginesLeftAllowed(array $aiCrawlers): array
    {
        return array_values(array_udiff(AiCrawlerListUpdater::ANSWER_ENGINES, $aiCrawlers, 'strcasecmp'));
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
        // An index of pages published for models to read is the opposite of what a site staying out of search engines asked for, and handing one over while robots.txt forbids everything would be saying both at once
        if ($this->isPrivate()) {
            return null;
        }

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

    // Only declared when the index is really deployed: a "Sitemap:" line pointing at a 404 is a Search Console error, and c975l:sitemaps:create writes no index at all until a provider has urls to declare. Never on a private site, where handing crawlers the list of everything the same file just forbade would only invite them in - the index itself is left written, being what the site's own tooling reads
    private function sitemapIndexUrl(): ?string
    {
        if ($this->isPrivate()) {
            return null;
        }

        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');

        return '' !== $siteUrl && is_file($this->publicFolder . '/sitemap-index.xml')
            ? $siteUrl . '/sitemap-index.xml'
            : null;
    }

    // Whether this site declared itself out of search engines - read in three places, and by SeoFilesHealthCheckProvider too, which is what keeps the "Disallow: /" it would otherwise report as the worst misconfiguration there is from being reported as one here
    private function isPrivate(): bool
    {
        return (bool) $this->configService->get('seo-robots-private');
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

        return array_values(array_filter(array_map($this->text(...), $value)));
    }
}
