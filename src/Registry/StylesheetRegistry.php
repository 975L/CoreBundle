<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;

class StylesheetRegistry
{
    /** @var BundleStylesheetProviderInterface[] */
    private array $providers = [];

    public function addProvider(BundleStylesheetProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return string[] */
    public function all(): array
    {
        $stylesheets = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getStylesheets() as $url) {
                if (!in_array($url, $stylesheets, true)) {
                    $stylesheets[] = $url;
                }
            }
        }

        return $stylesheets;
    }

    // Whether a registered stylesheet path is an absolute/external URL (a CDN resource like cookieconsent.min.css) rather than a local public/ path resolved through Symfony's asset package - shared by StylesheetExtension and StylesheetCacheWarmer so "what counts as external" stays defined once
    public static function isExternal(string $path): bool
    {
        return str_starts_with($path, 'http');
    }
}
