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
    private const APP_ASSETS_PREFIX = 'assets/';

    private const GENERATED_PREFIX = 'bundles/build/';

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

    // Whether it is one of the app's own sheets under assets/ (its theme files, see SiteBundle's readme) rather than a bundle's compiled one under public/. Registering them is what puts them in the single concatenated bundles/build/site.css instead of one <link> each: AssetMapper never merges CSS, so a site splitting its theme per bundle would otherwise pay one request per file. Same reason isExternal() lives here - both callers have to agree on what a path means
    public static function isAppAsset(string $path): bool
    {
        return str_starts_with($path, self::APP_ASSETS_PREFIX);
    }

    // Whether it is one of the sheets written at runtime under public/bundles/build/ - the theme variables and the uploaded @font-face rules compiled from the back-office, plus the concatenated site.css itself. None of them goes through an asset manifest, so callers version them by their own mtime rather than by a hash. Same reason isExternal()/isAppAsset() live here: what a registered path means stays defined once
    public static function isGenerated(string $path): bool
    {
        return str_starts_with($path, self::GENERATED_PREFIX);
    }

    // The AssetMapper logical path of an app asset, i.e. what asset() resolves in dev - its root being the assets/ directory itself, the prefix registered paths carry is not part of it
    public static function logicalPath(string $path): string
    {
        return self::isAppAsset($path) ? substr($path, \strlen(self::APP_ASSETS_PREFIX)) : $path;
    }
}
