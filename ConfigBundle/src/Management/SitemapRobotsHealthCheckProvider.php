<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\RobotsTxtMatcher;
use c975L\ConfigBundle\Service\SeoFilesClient;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Cross-checks what the site hands to search engines against what it forbids them: every url its sitemaps declare, tested against the robots.txt actually deployed. SeoFilesHealthCheckProvider only catches the blanket "Disallow: /" under "User-agent: *" - a rule scoped to a path keeps declared urls out of the results just as effectively, and silently, which is what Search Console reports as "Blocked by robots.txt" on urls the sitemap itself declares. A contradiction the site states about itself, like the noindex ContentQualityAnalyzer looks for, and one nothing else can see: the page answers 200 and reads perfectly well to anyone but a crawler
class SitemapRobotsHealthCheckProvider implements HealthCheckProviderInterface
{
    // Checked once for the whole site, not once per page: HealthCheckController lists this kind in SITE_WIDE_KINDS
    public const KIND = 'sitemap-robots';

    public function __construct(
        private readonly SiteUrlResolver $siteUrlResolver,
        private readonly SeoFilesClient $seoFilesClient,
        private readonly TranslatorInterface $translator,
        // Every SitemapProviderInterface implementation, whatever the bundle it comes from - the same iterator SitemapWriter is given, so the urls tested here are exactly the ones written to the sitemaps (see services.yaml)
        private readonly iterable $sitemapProviders,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $siteUrl = $this->siteUrlResolver->siteUrl();
        if (null === $siteUrl) {
            return [];
        }

        $robotsUrl = $siteUrl . '/robots.txt';

        try {
            $file = $this->seoFilesClient->fetch($robotsUrl);
        } catch (\Throwable $e) {
            return [$this->errorRow($robotsUrl, 'label.health_check_sitemap_robots_call_failed', ['%message%' => $e->getMessage()])];
        }

        // Nothing to cross-check against, and nothing to report either: a missing robots.txt is already the error SeoFilesHealthCheckProvider raises, and repeating it here would leave two rows for one defect
        if ($file['statusCode'] >= 400 || '' === trim($file['content'])) {
            return [];
        }

        return $this->crossCheck($robotsUrl, $file['content'], (string) parse_url($siteUrl, \PHP_URL_HOST));
    }

    // The urls the site's own robots.txt refuses, one row each, plus the summary row carrying the count either way - a check whose light goes out entirely when it passes would be indistinguishable from one that never ran
    private function crossCheck(string $robotsUrl, string $content, string $siteHost): array
    {
        // Read as Googlebot, not as the wildcard group: a file scoping its rules to "User-agent: Googlebot" would otherwise be cross-checked against rules that don't apply to it, and for() already falls back to the wildcard group when the file never names Googlebot - one pass covers both. It is also the crawler whose "Blocked by robots.txt" message this check reproduces
        $matcher = RobotsTxtMatcher::for($content, 'Googlebot');
        $rows = [];
        $checked = 0;

        foreach ($this->collectUrls($siteHost) as $url) {
            ++$checked;
            if ($matcher->allows($this->pathOf($url))) {
                continue;
            }

            $rows[] = [
                'url' => $url,
                'label' => $this->labelFromUrl($url),
                'status' => HealthCheckResult::STATUS_ERROR,
                'summary' => $this->translator->trans('label.health_check_sitemap_robots_blocked', [], 'config'),
                'details' => ['path' => $this->pathOf($url)],
                'editUrl' => null,
            ];
        }

        array_unshift($rows, $this->summaryRow($robotsUrl, $checked, count($rows)));

        return $rows;
    }

    // Every url declared by every sitemap provider, kept to the host robots.txt actually governs: a provider declaring an url on another host (a cdn, another site of the same admin) is not bound by this file, and testing it here would report a rule that doesn't apply to it
    // @return list<string>
    private function collectUrls(string $siteHost): array
    {
        $urls = [];
        /** @var SitemapProviderInterface $provider */
        foreach ($this->sitemapProviders as $provider) {
            foreach ($provider->getUrls() as $url) {
                $location = $this->location($url);
                if (null === $location) {
                    continue;
                }

                if (strtolower((string) parse_url($location, \PHP_URL_HOST)) === strtolower($siteHost)) {
                    $urls[] = $location;
                }
            }
        }

        return $urls;
    }

    // Taken as mixed on purpose, like SeoFilesWriter's own reader: SitemapProviderInterface declares the shape, but the implementations are other bundles' code, and one incomplete row has to be skipped rather than take the whole check down
    private function location(mixed $url): ?string
    {
        $location = \is_array($url) ? ($url['loc'] ?? null) : null;

        return \is_string($location) && '' !== $location ? $location : null;
    }

    private function summaryRow(string $robotsUrl, int $checked, int $blocked): array
    {
        return [
            'url' => $robotsUrl,
            'label' => 'robots.txt',
            'status' => $blocked > 0 ? HealthCheckResult::STATUS_ERROR : HealthCheckResult::STATUS_OK,
            'summary' => $blocked > 0
                ? $this->translator->trans('label.health_check_sitemap_robots_summary_blocked', ['%blocked%' => $blocked, '%count%' => $checked], 'config')
                : $this->translator->trans('label.health_check_sitemap_robots_summary_ok', ['%count%' => $checked], 'config'),
            'details' => ['checked' => $checked, 'blocked' => $blocked],
            'editUrl' => null,
        ];
    }

    // What robots.txt matches its rules against: the path and, when there is one, the query string - not the host, which the file never names
    private function pathOf(string $url): string
    {
        $path = (string) parse_url($url, \PHP_URL_PATH);
        $query = parse_url($url, \PHP_URL_QUERY);

        return ('' === $path ? '/' : $path) . (\is_string($query) && '' !== $query ? '?' . $query : '');
    }

    private function labelFromUrl(string $url): string
    {
        $path = trim((string) parse_url($url, \PHP_URL_PATH), '/');

        return '' === $path ? $url : $path;
    }

    private function errorRow(string $url, string $translationId, array $parameters): array
    {
        return [
            'url' => $url,
            'label' => 'robots.txt',
            'status' => HealthCheckResult::STATUS_ERROR,
            'summary' => $this->translator->trans($translationId, $parameters, 'config'),
            'details' => ['error' => $parameters['%message%'] ?? null],
            'editUrl' => null,
        ];
    }
}
