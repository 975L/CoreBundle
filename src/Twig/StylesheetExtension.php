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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class StylesheetExtension extends AbstractExtension
{
    public function __construct(
        private StylesheetRegistry $registry,
        private Packages $packages,
        private RequestStack $requestStack,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('bundle_stylesheets', [$this, 'getBundleStylesheets']),
        ];
    }

    /** @return string[] */
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

        return array_map(
            fn (string $path) => StylesheetRegistry::isExternal($path)
                ? $path
                : $baseUrl . $this->packages->getUrl($path),
            $this->registry->all()
        );
    }

    // bundles/build/site.css is generated at cache-warmup time (see StylesheetCacheWarmer), outside any asset-manifest build step - Packages::getUrl() has no way to know its content changed on a later warmup/deploy, so its own versioning can't be relied on for this specific path. Appending the compiled file's own mtime as a query param busts caches independently of that.
    private function addCacheBustingParam(string $url, int $mtime): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . 'v=' . $mtime;
    }
}
