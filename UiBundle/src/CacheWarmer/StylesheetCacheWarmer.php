<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\CacheWarmer;

use c975L\UiBundle\Registry\StylesheetManagementRegistry;
use c975L\UiBundle\Registry\StylesheetRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class StylesheetCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly StylesheetRegistry $stylesheetRegistry,
        private readonly StylesheetManagementRegistry $stylesheetManagementRegistry,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->compileAll();

        return [];
    }

    // Rebuilds site.css/admin.css from every currently registered stylesheet - public so it can also be called at runtime (see ThemeVariablesCssListener) when a contributed stylesheet's content changes (e.g. a theme config update), instead of waiting for the next cache:warmup
    public function compileAll(): void
    {
        $buildPath = $this->projectDir . '/public/bundles/build';
        if (!is_dir($buildPath) && !@mkdir($buildPath, 0775, true) && !is_dir($buildPath)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory.', $buildPath));
        }

        $this->write($buildPath . '/site.css', $this->compile($this->stylesheetRegistry->all()));
        $this->write($buildPath . '/admin.css', $this->compile($this->stylesheetManagementRegistry->all()));
    }

    // Writes through a temp file + rename() instead of file_put_contents() directly on the served path - rename() is atomic on the same filesystem, so a request served mid-warmup never sees a truncated file. Throws (rather than failing silently) on any I/O error - this warmer isn't wrapped in a try/catch by Symfony's CacheWarmerAggregate, so a thrown exception surfaces as a loud cache:warmup/cache:clear failure instead of leaving a stale or missing compiled stylesheet
    private function write(string $path, string $content): void
    {
        $tmpPath = $path . '.' . uniqid('', true) . '.tmp';
        if (false === @file_put_contents($tmpPath, $content) || !@rename($tmpPath, $path)) {
            throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
        }
    }

    // Concatenates the content of every local stylesheet, skipping absolute URLs (CDN resources like cookieconsent.min.css), which stay served as separate <link> tags
    private function compile(array $stylesheets): string
    {
        $content = [];
        foreach ($stylesheets as $stylesheet) {
            if (StylesheetRegistry::isExternal($stylesheet)) {
                continue;
            }

            // Some contributed stylesheets are generated at runtime (e.g. ThemeVariablesCssListener) and may not exist yet on a fresh install
            // An app's own sheet under assets/ is read from the project root: it is an AssetMapper source, never copied to public/ - concatenating it here is what keeps a site's theme files down to the single request the bundles already share
            $path = StylesheetRegistry::isAppAsset($stylesheet)
                ? $this->projectDir . '/' . $stylesheet
                : $this->projectDir . '/public/' . $stylesheet;
            if (is_file($path)) {
                $content[] = self::stripComments(self::stripByteOrderMark((string) file_get_contents($path)));
            }
        }

        return implode("\n", $content);
    }

    // A browser silently drops a UTF-8 BOM at the very start of a stylesheet, so one costs nothing while each file is served on its own <link>. Concatenated, every BOM but the first lands mid-file, where it is just a stray character: the rule following it becomes a parse error and is thrown away whole.
    // Sass writes one into any --style=compressed output holding a non-ASCII byte, so this is not hypothetical - it is how bundles/build/site.css lost UiBundle's entire @layer of token defaults.
    private static function stripByteOrderMark(string $css): string
    {
        return str_starts_with($css, "\u{FEFF}") ? substr($css, 3) : $css;
    }

    // Comments are dropped here rather than from the sources: a site's own assets/styles/themes/*.css is deliberately almost all comments - every token ships commented out at its default, which is what makes the file readable - and each bundle's compiled sheet keeps its own header. Concatenated, that is a quarter of the bytes sent to every visitor for something no browser reads.
    // "/*!" is left in place, the convention marking a header that must survive minification (a license, an authorship notice).
    private static function stripComments(string $css): string
    {
        return preg_replace('#/\*(?!!).*?\*/#s', '', $css) ?? $css;
    }
}
