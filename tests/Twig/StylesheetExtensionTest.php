<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Registry\StylesheetRegistry;
use c975L\UiBundle\Twig\StylesheetExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class StylesheetExtensionTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory, so the compiled-file existence check can be exercised safely with real filesystem reads
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/stylesheet-extension-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->projectDir)) {
            $this->removeDirectory($this->projectDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    // Simulates a successful StylesheetCacheWarmer run, so the "not debug" branch's file existence check passes
    private function createCompiledSiteCss(): void
    {
        mkdir($this->projectDir . '/public/bundles/build', 0777, true);
        file_put_contents($this->projectDir . '/public/bundles/build/site.css', '');
    }

    private function createRequestStack(?Request $request): RequestStack
    {
        $requestStack = $this->createStub(RequestStack::class);
        $requestStack->method('getCurrentRequest')->willReturn($request);

        return $requestStack;
    }

    // Relative paths are resolved through Packages::getUrl() and prefixed with the current request's scheme+host, so stylesheets keep working behind a CDN/asset-mapper versioned URL
    public function testGetBundleStylesheetsPrefixesRelativePathWithRequestBaseUrlInDebug(): void
    {
        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['css/styles.min.css']);

        $packages = $this->createMock(Packages::class);
        $packages->expects($this->once())->method('getUrl')->with('css/styles.min.css')->willReturn('/build/css/styles-abc123.min.css');

        $request = Request::create('https://example.com/page');

        $extension = new StylesheetExtension($registry, $packages, $this->createRequestStack($request), true, $this->projectDir);

        $this->assertSame(
            ['https://example.com/build/css/styles-abc123.min.css'],
            $extension->getBundleStylesheets()
        );
    }

    // Absolute URLs (CDN resources) are passed through untouched, never run through Packages::getUrl()
    public function testGetBundleStylesheetsKeepsAbsoluteHttpUrlUnchangedInDebug(): void
    {
        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['https://cdn.example.com/lib.css']);

        $packages = $this->createMock(Packages::class);
        $packages->expects($this->never())->method('getUrl');

        $extension = new StylesheetExtension(
            $registry,
            $packages,
            $this->createRequestStack(Request::create('https://example.com/')),
            true,
            $this->projectDir
        );

        $this->assertSame(['https://cdn.example.com/lib.css'], $extension->getBundleStylesheets());
    }

    // Without a current request (e.g. CLI context), the base URL is simply empty
    public function testGetBundleStylesheetsUsesEmptyBaseUrlWhenNoCurrentRequestInDebug(): void
    {
        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['css/styles.min.css']);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturn('/css/styles.min.css');

        $extension = new StylesheetExtension($registry, $packages, $this->createRequestStack(null), true, $this->projectDir);

        $this->assertSame(['/css/styles.min.css'], $extension->getBundleStylesheets());
    }

    // Outside debug, links to the single file compiled on disk by StylesheetCacheWarmer instead of the per-bundle list, so only one local <link> request is made
    public function testGetBundleStylesheetsReturnsCompiledFileUrlWhenNotDebug(): void
    {
        $this->createCompiledSiteCss();
        $mtime = filemtime($this->projectDir . '/public/bundles/build/site.css');

        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['bundles/c975lui/css/styles.min.css']);

        $packages = $this->createMock(Packages::class);
        $packages->expects($this->once())->method('getUrl')->with('bundles/build/site.css')->willReturn('/bundles/build/site-abc123.css');

        $extension = new StylesheetExtension(
            $registry,
            $packages,
            $this->createRequestStack(Request::create('https://example.com/')),
            false,
            $this->projectDir
        );

        $this->assertSame(
            ["https://example.com/bundles/build/site-abc123.css?v={$mtime}"],
            $extension->getBundleStylesheets()
        );
    }

    // Absolute URLs (CDN resources like cookieconsent.min.css) stay served on their own even when not debug, since they are excluded from the compiled file by StylesheetCacheWarmer
    public function testGetBundleStylesheetsKeepsAbsoluteHttpUrlAlongsideCompiledFileWhenNotDebug(): void
    {
        $this->createCompiledSiteCss();
        $mtime = filemtime($this->projectDir . '/public/bundles/build/site.css');

        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn([
            'bundles/c975lui/css/styles.min.css',
            'https://cdn.example.com/lib.css',
        ]);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturn('/bundles/build/site-abc123.css');

        $extension = new StylesheetExtension(
            $registry,
            $packages,
            $this->createRequestStack(Request::create('https://example.com/')),
            false,
            $this->projectDir
        );

        $this->assertSame(
            ["https://example.com/bundles/build/site-abc123.css?v={$mtime}", 'https://cdn.example.com/lib.css'],
            $extension->getBundleStylesheets()
        );
    }

    // The compiled file's own mtime busts caches independently of whatever Packages::getUrl() returns - regression guard for stale CSS being served after a deploy that only changed a stylesheet's content
    public function testGetBundleStylesheetsCacheBustingParamChangesWhenCompiledFileIsRewritten(): void
    {
        $this->createCompiledSiteCss();

        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['bundles/c975lui/css/styles.min.css']);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturn('/bundles/build/site.css');

        $extension = new StylesheetExtension(
            $registry,
            $packages,
            $this->createRequestStack(Request::create('https://example.com/')),
            false,
            $this->projectDir
        );

        $first = $extension->getBundleStylesheets()[0];

        touch($this->projectDir . '/public/bundles/build/site.css', filemtime($this->projectDir . '/public/bundles/build/site.css') + 1);
        $second = $extension->getBundleStylesheets()[0];

        $this->assertNotSame($first, $second);
    }

    // Packages::getUrl() may already return its own versioned query string (e.g. an app-level manifest strategy) - the cache-busting param must be appended with "&", not overwrite/duplicate the "?"
    public function testGetBundleStylesheetsAppendsCacheBustingParamToAnExistingQueryString(): void
    {
        $this->createCompiledSiteCss();
        $mtime = filemtime($this->projectDir . '/public/bundles/build/site.css');

        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['bundles/c975lui/css/styles.min.css']);

        $packages = $this->createStub(Packages::class);
        $packages->method('getUrl')->willReturn('/bundles/build/site.css?foo=bar');

        $extension = new StylesheetExtension(
            $registry,
            $packages,
            $this->createRequestStack(Request::create('https://example.com/')),
            false,
            $this->projectDir
        );

        $this->assertSame(
            ["https://example.com/bundles/build/site.css?foo=bar&v={$mtime}"],
            $extension->getBundleStylesheets()
        );
    }

    // Regression guard: when the compiled file hasn't been produced yet (first request right after a deploy, before cache:warmup runs, or a failed warmup), falls back to the per-bundle list instead of linking a 404 and losing every local stylesheet at once
    public function testGetBundleStylesheetsFallsBackToPerBundleListWhenCompiledFileIsMissingNotDebug(): void
    {
        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn(['bundles/c975lui/css/styles.min.css']);

        $packages = $this->createMock(Packages::class);
        $packages->expects($this->once())->method('getUrl')->with('bundles/c975lui/css/styles.min.css')->willReturn('/bundles/c975lui/css/styles-abc123.css');

        $extension = new StylesheetExtension(
            $registry,
            $packages,
            $this->createRequestStack(Request::create('https://example.com/')),
            false,
            $this->projectDir
        );

        $this->assertSame(
            ['https://example.com/bundles/c975lui/css/styles-abc123.css'],
            $extension->getBundleStylesheets()
        );
    }

    public function testGetFunctionsRegistersBundleStylesheetsFunction(): void
    {
        $extension = new StylesheetExtension(
            $this->createStub(StylesheetRegistry::class),
            $this->createStub(Packages::class),
            $this->createRequestStack(null),
            true,
            $this->projectDir
        );
        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('bundle_stylesheets', $functions[0]->getName());
    }
}
