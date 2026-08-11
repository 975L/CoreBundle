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
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class StylesheetCacheWarmer implements CacheWarmerInterface
{
    public function __construct(
        private readonly StylesheetRegistry $stylesheetRegistry,
        private readonly StylesheetManagementRegistry $stylesheetManagementRegistry,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        // Optional so an app running without AssetMapper still gets a compiled sheet: only the url() of its own assets/ files then stay as written (see resolveAssetPath)
        private readonly ?AssetMapperInterface $assetMapper = null,
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
        $imports = [];
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
                $css = self::stripComments(self::stripByteOrderMark((string) file_get_contents($path)));
                $content[] = $this->rewriteUrls($this->extractImports($css, $stylesheet, $imports), $stylesheet);
            }
        }

        // Every collected @import goes first: the spec requires them before any other rule, so those of a sheet concatenated after the first one would be invalid where they stand and dropped
        return implode("\n", array_merge($imports, $content));
    }

    // Pulls a sheet's @import rules out of its body and collects them for the head of the compiled file - a site's app.css is contributed last, right where its own imports stop being read. Their relative paths are resolved here for the same reason url() is: the compiled file is served from another directory than the sheet that wrote them
    private function extractImports(string $css, string $stylesheet, array &$imports): string
    {
        return preg_replace_callback(
            '#@import\s+(?:url\(\s*(["\']?)(?<url>[^"\')]+)\1\s*\)|(["\'])(?<quoted>[^"\']+)\3)(?<media>[^;]*);#i',
            function (array $matches) use ($stylesheet, &$imports): string {
                // Both groups are always set, one of the two alternatives having matched: the empty one is the branch that did not
                $url = '' !== $matches['url'] ? $matches['url'] : $matches['quoted'];
                $statement = sprintf('@import url("%s")%s;', $this->resolveAssetPath($url, $stylesheet) ?? $url, rtrim($matches['media']));
                if (!in_array($statement, $imports, true)) {
                    $imports[] = $statement;
                }

                return '';
            },
            $css
        ) ?? $css;
    }

    // Rewrites the relative url() of a sheet against the compiled file's own location: served from bundles/build/, the "../fonts/Cabin.ttf" of a site's @font-face - correct while that sheet had its own <link> - points at a file that is not there
    private function rewriteUrls(string $css, string $stylesheet): string
    {
        return preg_replace_callback(
            '#\burl\(\s*(["\']?)([^"\')]+)\1\s*\)#i',
            function (array $matches) use ($stylesheet): string {
                $resolved = $this->resolveAssetPath(trim($matches[2]), $stylesheet);

                return null === $resolved ? $matches[0] : sprintf('url("%s")', $resolved);
            },
            $css
        ) ?? $css;
    }

    // The path a reference must carry once its sheet is concatenated: an app asset goes through AssetMapper, which alone knows the versioned name its file is published under, while a bundle's sheet under public/ resolves against the site root. Returns null for whatever already resolves on its own (absolute URL, root-relative path, data:, fragment) or cannot be resolved, leaving the reference untouched
    private function resolveAssetPath(string $url, string $stylesheet): ?string
    {
        if ('' === $url || str_starts_with($url, '/') || str_starts_with($url, '#') || 1 === preg_match('#^[a-z][a-z0-9+.-]*:#i', $url)) {
            return null;
        }

        // A query string or a fragment (a font's "#iefix", a sprite id) is no part of the file to look up, but has to survive the rewrite
        $length = strcspn($url, '?#');
        $suffix = substr($url, $length);
        $directory = \dirname(StylesheetRegistry::logicalPath($stylesheet));
        $path = self::normalizePath(('.' === $directory ? '' : $directory . '/') . substr($url, 0, $length));
        if (null === $path) {
            return null;
        }

        if (!StylesheetRegistry::isAppAsset($stylesheet)) {
            return '/' . $path . $suffix;
        }

        $publicPath = $this->assetMapper?->getPublicPath($path);

        return null === $publicPath ? null : $publicPath . $suffix;
    }

    // Collapses the "." and ".." segments of a path joined to its sheet's directory, AssetMapper logical paths and public ones alike knowing nothing of them. Returns null for a path climbing above its own root, which no longer designates anything either way
    private static function normalizePath(string $path): ?string
    {
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ('' === $segment || '.' === $segment) {
                continue;
            }

            if ('..' === $segment) {
                if ([] === $segments) {
                    return null;
                }

                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return [] === $segments ? null : implode('/', $segments);
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
        // Every comment is matched and the callback decides, rather than the pattern excluding "/*!" - such a pattern skips the header and restarts on the "/*" held by the "*/*,::before" that follows it in a minified sheet, swallowing the file down to the next "*/"
        return preg_replace_callback(
            '#/\*.*?\*/#s',
            static fn (array $matches): string => str_starts_with($matches[0], '/*!') ? $matches[0] : '',
            $css
        ) ?? $css;
    }
}
