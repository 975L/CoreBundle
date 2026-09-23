<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Merges the routes contributed by every LinkableRouteProviderInterface (see readme)
class LinkableRouteRegistry
{
    private ?array $routes = null;

    // The tags each entry is cached under, null for an entry whose provider declares none (see LinkableRouteCacheTagsInterface)
    /** @var array<string, string[]|null> */
    private array $cacheTags = [];

    // @param iterable<LinkableRouteProviderInterface> $providers
    public function __construct(
        private readonly iterable $providers,
        private readonly TranslatorInterface $translator,
        private readonly ?TagAwareCacheInterface $cache = null,
    ) {
    }

    // The tags a menu item pointing at this entry can be cached under, null when its provider cannot say when it changes
    /** @return string[]|null */
    public function cacheTags(string $key): ?array
    {
        $this->routes();

        return $this->cacheTags[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return isset($this->routes()[$key]);
    }

    public function get(string $key): ?array
    {
        return $this->routes()[$key] ?? null;
    }

    public function all(): array
    {
        return $this->routes();
    }

    // What an entry is shown as, wherever it is: the target picker, the rendered menu item and SiteCreateCommand all read it here rather than translating the raw entry themselves, an entry standing for a database row carrying a literal label ('translation_domain' at false) where a bundle's own route carries a translation key
    public function label(string $key): string
    {
        $entry = $this->get($key);
        if (null === $entry) {
            return '';
        }

        return false === $entry['translation_domain'] ? $entry['label'] : $this->translator->trans($entry['label'], [], $entry['translation_domain']);
    }

    // What the back office's target select shows, which isn't always what the menu item itself reads: an entry standing for a database row says what it is there ("Galerie - Paysages"), among every page of the site, and keeps its bare title in the rendered navbar
    public function pickerLabel(string $key): string
    {
        return $this->get($key)['picker_label'] ?? $this->label($key);
    }

    // Resolved on first read rather than in the constructor: a provider listing one entry per row of its own data queries the database to do so, and this service is held by MenuExtension, instantiated on every rendered page - a menu made of pages alone never pays for it
    private function routes(): array
    {
        if (null !== $this->routes) {
            return $this->routes;
        }

        // Merged by hand rather than through ProviderMerger: a key is what a menu item stores ("route:KEY") and has to survive the merge as it was written, where array_merge() renumbers the integer ones - an entry keyed on a row's id would come out pointing at a position (see LinkableRouteProviderInterface). "Last provider wins" is kept, the same as everywhere else
        $this->routes = [];
        foreach ($this->providers as $provider) {
            $tags = $provider instanceof LinkableRouteCacheTagsInterface ? $provider->getLinkableRouteCacheTags() : null;

            foreach ($this->entries($provider, $tags) as $key => $entry) {
                $this->cacheTags[$key] = $tags;
                // Filled in once here so every consumer reads the same shape, the common case being a key that is itself a route name with nothing to fill (see LinkableRouteProviderInterface). "locales" at null rather than at the site's own languages: it says the provider did not answer, which is not the same as answering "every one of them" - and only the provider knows whether its route has a localised twin at all
                $this->routes[$key] = $entry + ['route' => $key, 'params' => [], 'translation_domain' => false, 'locales' => null];
            }
        }

        return $this->routes;
    }

    // A provider declaring its tags is read through the cache, in the language being read - its labels may be the rows' own translated names; the others live, as they always were
    /**
     * @param string[]|null $tags
     *
     * @return array<string, array<string, mixed>>
     */
    private function entries(LinkableRouteProviderInterface $provider, ?array $tags): array
    {
        if (null === $tags || null === $this->cache) {
            return $provider->getLinkableRoutes();
        }

        $key = 'linkable_routes_' . hash('xxh128', $provider::class . "\0" . $this->translator->getLocale());

        return $this->cache->get($key, static function (ItemInterface $item) use ($provider, $tags): array {
            $item->expiresAfter(null);
            $item->tag($tags);

            return $provider->getLinkableRoutes();
        });
    }
}
