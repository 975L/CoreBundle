<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Registry\StylesheetRegistry;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

class StylesheetExtension
{
    public function __construct(
        private readonly StylesheetRegistry $registry,
        private readonly Packages $packages,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'kernel.debug')]
        private readonly bool $debug,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /** @return string[] */
    #[AsTwigFunction('bundle_stylesheets')]
    public function getBundleStylesheets(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $baseUrl = $request ? $request->getSchemeAndHttpHost() : '';

        // In dev, keeps each bundle's stylesheet separate for instant reload on every CSS edit; in prod, links to the single file compiled by StylesheetCacheWarmer instead, plus any absolute URL (CDN resources like cookieconsent.min.css) which stays served on its own. Falls back to the per-bundle list below when that compiled file doesn't exist yet (e.g. the first request right after a deploy, before cache:warmup has run) instead of linking a 404 and losing every local stylesheet at once. A single filemtime() call doubles as both the existence check and the cache-busting value below, instead of two separate stat()s.
        $compiledPath = $this->projectDir . '/public/bundles/build/site.css';
        $compiledMtime = $this->debug ? false : @filemtime($compiledPath);
        if (false !== $compiledMtime) {
            $externals = array_filter($this->registry->all(), StylesheetRegistry::isExternal(...));

            return [
                $this->addCacheBustingParam($baseUrl . $this->packages->getUrl('bundles/build/site.css'), $compiledMtime),
                ...array_values($externals),
            ];
        }

        return array_map(fn (string $path) => $this->resolve($path, $baseUrl), $this->registry->all());
    }

    // Turns a registered stylesheet path into the URL linked in the page
    private function resolve(string $path, string $baseUrl): string
    {
        if (StylesheetRegistry::isExternal($path)) {
            return $path;
        }

        // An app's own sheet under assets/ is served by AssetMapper, whose root is that directory: the prefix the registered path carries is not part of its logical path
        $url = $baseUrl . $this->packages->getUrl(StylesheetRegistry::logicalPath($path));

        // A generated sheet carries no AssetMapper hash, so its own mtime is the only thing that can bust the browser's copy - without it, a theme color or font changed in the back-office keeps showing the previous value until a hard reload, the stale file leaving --c975l-color-primary/--c975l-font-family-title undefined and every rule falling back to the shipped default
        $mtime = StylesheetRegistry::isGenerated($path) ? @filemtime($this->projectDir . '/public/' . $path) : false;

        return false !== $mtime ? $this->addCacheBustingParam($url, $mtime) : $url;
    }

    // A stylesheet under bundles/build/ is generated outside any asset-manifest build step (see StylesheetCacheWarmer and ThemeVariablesCssListener) - Packages::getUrl() has no way to know its content changed on a later warmup, deploy or back-office edit, so its own versioning can't be relied on for those paths. Appending the file's own mtime as a query param busts caches independently of that.
    private function addCacheBustingParam(string $url, int $mtime): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . $mtime;
    }
}
