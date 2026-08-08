<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Symfony\Contracts\Translation\TranslatorInterface;

// Merges the routes contributed by every LinkableRouteProviderInterface (see readme)
class LinkableRouteRegistry
{
    private ?array $routes = null;

    // @param iterable<LinkableRouteProviderInterface> $providers
    public function __construct(
        private readonly iterable $providers,
        private readonly TranslatorInterface $translator,
    ) {
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
            foreach ($provider->getLinkableRoutes() as $key => $entry) {
                // Filled in once here so every consumer reads the same shape, the common case being a key that is itself a route name with nothing to fill (see LinkableRouteProviderInterface)
                $this->routes[$key] = $entry + ['route' => $key, 'params' => [], 'translation_domain' => false];
            }
        }

        return $this->routes;
    }
}
