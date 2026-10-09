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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Twig\Environment;

// Renders the Atom feed of each registered FeedProviderInterface, and lists the ones worth announcing in the <head> - a bundle only ever supplies entries, as it does urls to SitemapWriter
class FeedRenderer
{
    public const int ENTRIES_LIMIT = 20;

    // Only the names are cached, the titles being translated in the language of each page
    private const string CACHE_KEY = 'c975l_feed_names';
    private const int CACHE_LIFETIME = 3600;
    private const int SUMMARY_LENGTH = 300;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $environment,
        private readonly CacheInterface $cache,
        // Every FeedProviderInterface implementation, tagged automatically by TaggedInterfacePass (see services.yaml)
        private readonly iterable $feedProviders,
    ) {
    }

    // Feeds holding at least one entry, as name => title - cached so the <head> of a page never queries the database
    public function available(): array
    {
        if ($this->isClosed()) {
            return [];
        }

        $providers = $this->providers();
        $names = $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($providers): array {
            $item->expiresAfter(self::CACHE_LIFETIME);

            return array_keys(array_filter($providers, fn (FeedProviderInterface $provider): bool => [] !== $provider->getEntries(1)));
        });

        $feeds = [];
        foreach ($names as $name) {
            if (isset($providers[$name])) {
                $feeds[$name] = $providers[$name]->getFeedTitle();
            }
        }

        return $feeds;
    }

    // Atom document of the feed named $name, null when there is no such feed or nothing in it
    public function render(string $name, string $language): ?string
    {
        $provider = $this->providers()[$name] ?? null;
        if (null === $provider || $this->isClosed()) {
            return null;
        }

        $entries = $provider->getEntries(self::ENTRIES_LIMIT);
        if ([] === $entries) {
            return null;
        }

        $siteUrl = rtrim((string) $this->configService->get('site-url'), '/');

        return $this->environment->render('@c975LConfig/feeds/atom.xml.twig', [
            // Named after the site as well, a feed reader listing feeds from many of them
            'title' => implode(' - ', array_filter([(string) $this->configService->get('site-name'), $provider->getFeedTitle()])),
            'language' => $language,
            'siteUrl' => $siteUrl . '/',
            'selfUrl' => $siteUrl . '/feed/' . $name . '.xml',
            'author' => $this->configService->get('site-author') ?: $this->configService->get('site-name'),
            'updated' => max(array_map(fn (array $entry): \DateTimeInterface => $entry['updated'], $entries)),
            'entries' => array_map(fn (array $entry): array => [...$entry, 'summary' => $this->plainText($entry['summary'] ?? null)], $entries),
        ]);
    }

    // Providers keyed by feed name, two of them sharing one being a naming mistake to fix rather than to absorb
    private function providers(): array
    {
        $providers = [];
        foreach ($this->feedProviders as $provider) {
            /** @var FeedProviderInterface $provider */
            $name = $provider->getFeedName();
            if (isset($providers[$name])) {
                throw new \LogicException(sprintf('The feed name "%s" is declared by more than one FeedProviderInterface, each one must use its own.', $name));
            }
            $providers[$name] = $provider;
        }

        return $providers;
    }

    // No absolute url to give before "site-url" is configured, and a site kept out of search engines does not push its content out either
    private function isClosed(): bool
    {
        return '' === trim((string) $this->configService->get('site-url')) || (bool) $this->configService->get('seo-robots-private');
    }

    // Rich text reduced to a single line of plain text, cut on a word
    private function plainText(?string $text): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), \ENT_QUOTES | \ENT_HTML5)));
        if ('' === $text) {
            return null;
        }
        if (mb_strlen($text) <= self::SUMMARY_LENGTH) {
            return $text;
        }

        $cut = mb_substr($text, 0, self::SUMMARY_LENGTH);
        $space = mb_strrpos($cut, ' ');

        return rtrim(false === $space ? $cut : mb_substr($cut, 0, $space), ' ,.;:') . '…';
    }
}
