<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\CacheWarmer;

use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use c975L\UiBundle\Registry\StylesheetManagementRegistry;
use c975L\UiBundle\Registry\StylesheetRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\AssetMapper\AssetMapperInterface;

class StylesheetCacheWarmerTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory, so real filesystem reads/writes can be exercised safely
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/stylesheet-cache-warmer-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    // Leaves no trace of the sandbox project directory once the test finishes
    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // Creates a CSS file with the given content at the given path relative to the sandbox public directory
    private function createCssFile(string $relativePathFromPublic, string $content): void
    {
        $path = $this->projectDir . '/public/' . $relativePathFromPublic;
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $content);
    }

    // Same, for one of the app's own sheets: those live under assets/, an AssetMapper source never copied to public/
    private function createAppAssetCssFile(string $relativePathFromProject, string $content): void
    {
        $path = $this->projectDir . '/' . $relativePathFromProject;
        $directory = \dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($path, $content);
    }

    // $publicPaths stands for the manifest AssetMapper builds, keyed by logical path - what an app asset's url() has to be rewritten to
    private function createWarmer(array $stylesheets, array $managementStylesheets, array $publicPaths = []): StylesheetCacheWarmer
    {
        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn($stylesheets);

        $managementRegistry = $this->createStub(StylesheetManagementRegistry::class);
        $managementRegistry->method('all')->willReturn($managementStylesheets);

        $assetMapper = $this->createStub(AssetMapperInterface::class);
        $assetMapper->method('getPublicPath')->willReturnCallback(
            static fn (string $logicalPath): ?string => $publicPaths[$logicalPath] ?? null
        );

        return new StylesheetCacheWarmer($registry, $managementRegistry, $this->projectDir, $assetMapper);
    }

    // Recursively deletes the sandbox directory tree created for a test
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function testWarmUpConcatenatesSiteStylesheetsInOrder(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.ui{color:red}');
        $this->createCssFile('bundles/c975lsite/css/styles.min.css', '.site{color:blue}');

        $warmer = $this->createWarmer(
            ['bundles/c975lui/css/styles.min.css', 'bundles/c975lsite/css/styles.min.css'],
            []
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            ".ui{color:red}\n.site{color:blue}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A site splitting its theme into one file per bundle would otherwise pay one <link> each, AssetMapper never merging CSS - registering them is what folds them into the single sheet the bundles share
    public function testWarmUpConcatenatesTheAppsOwnAssetsAlongsideTheBundlesOwn(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.ui{color:red}');
        $this->createAppAssetCssFile('assets/styles/themes/ui.css', ':root{--radius-card:16px}');
        $this->createAppAssetCssFile('assets/styles/themes/site.css', ':root{--scroll-offset:72px}');

        $warmer = $this->createWarmer(
            ['bundles/c975lui/css/styles.min.css', 'assets/styles/themes/ui.css', 'assets/styles/themes/site.css'],
            []
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            ".ui{color:red}\n:root{--radius-card:16px}\n:root{--scroll-offset:72px}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // Same path under public/ must not be picked up instead: the two roots are distinct namespaces
    public function testAnAppAssetIsNeverLookedUpUnderPublic(): void
    {
        $this->createCssFile('assets/styles/themes/ui.css', '.wrong{}');

        $warmer = $this->createWarmer(['assets/styles/themes/ui.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame('', file_get_contents($this->projectDir . '/public/bundles/build/site.css'));
    }

    // The concatenated sheet is served from bundles/build/, so the "../fonts/…" a site wrote in its @font-face points at nothing once inlined - only AssetMapper knows the versioned name that file is published under
    public function testWarmUpRewritesTheRelativeUrlOfAnAppAssetThroughAssetMapper(): void
    {
        $this->createAppAssetCssFile('assets/styles/_typography.css', '@font-face{src:url("../fonts/Cabin.ttf")}');

        $warmer = $this->createWarmer(
            ['assets/styles/_typography.css'],
            [],
            ['fonts/Cabin.ttf' => '/assets/fonts/Cabin-1a2b3c.ttf']
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '@font-face{src:url("/assets/fonts/Cabin-1a2b3c.ttf")}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A bundle's own sheet is published under public/, where its relative paths resolve against the site root rather than through the manifest
    public function testWarmUpResolvesTheRelativeUrlOfABundleSheetAgainstTheSiteRoot(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.ui{background:url(../images/logo.svg)}');

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '.ui{background:url("/bundles/c975lui/images/logo.svg")}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // Whatever already resolves on its own is left exactly as written - a rewritten data: URI would be a corrupted one
    public function testWarmUpLeavesUrlsThatAlreadyResolveUntouched(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.a{background:url(/images/logo.svg)}.b{background:url("https://cdn.example.com/logo.svg")}.c{background:url(data:image/gif;base64,R0lGOD)}.d{fill:url(#gradient)}');

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '.a{background:url(/images/logo.svg)}.b{background:url("https://cdn.example.com/logo.svg")}.c{background:url(data:image/gif;base64,R0lGOD)}.d{fill:url(#gradient)}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A font's "#iefix" or a sprite id is no part of the file to look up, but dropping it changes what the rule points at
    public function testWarmUpKeepsTheQueryAndFragmentOfARewrittenUrl(): void
    {
        $this->createAppAssetCssFile('assets/styles/_typography.css', '@font-face{src:url("../fonts/Cabin.woff2?v=2#iefix")}');

        $warmer = $this->createWarmer(
            ['assets/styles/_typography.css'],
            [],
            ['fonts/Cabin.woff2' => '/assets/fonts/Cabin-1a2b3c.woff2']
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '@font-face{src:url("/assets/fonts/Cabin-1a2b3c.woff2?v=2#iefix")}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A site's app.css is contributed last, so the @import opening it lands after every bundle's rules - where the spec makes it invalid and the browser drops it, imported sheets and all
    public function testWarmUpHoistsImportsToTheTopOfTheCompiledStylesheet(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.ui{color:red}');
        $this->createAppAssetCssFile('assets/styles/app.css', '@import "./_variables.css";.app{color:blue}');

        $warmer = $this->createWarmer(
            ['bundles/c975lui/css/styles.min.css', 'assets/styles/app.css'],
            [],
            ['styles/_variables.css' => '/assets/styles/_variables-1a2b3c.css']
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            "@import url(\"/assets/styles/_variables-1a2b3c.css\");\n.ui{color:red}\n.app{color:blue}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // An import of a CDN sheet resolves on its own and keeps its media query, which says when it applies at all
    public function testWarmUpHoistsAnAbsoluteImportWithItsMediaQuery(): void
    {
        $this->createAppAssetCssFile('assets/styles/app.css', '@import url("https://cdn.example.com/print.css") print;.app{color:blue}');

        $warmer = $this->createWarmer(['assets/styles/app.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            "@import url(\"https://cdn.example.com/print.css\") print;\n.app{color:blue}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A site's own theme files are almost entirely comments - every token ships commented out at its default - so the concatenated sheet drops them rather than sending a quarter of its bytes to every visitor for something no browser reads
    public function testWarmUpStripsCommentsFromTheConcatenatedStylesheet(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', "/* UiBundle */\n.ui{color:red}");
        $this->createAppAssetCssFile('assets/styles/themes/ui.css', ":root{\n    /* --radius-card: 10px; */\n    --scroll-offset: 72px;\n}");

        $warmer = $this->createWarmer(
            ['bundles/c975lui/css/styles.min.css', 'assets/styles/themes/ui.css'],
            []
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            "\n.ui{color:red}\n:root{\n    \n    --scroll-offset: 72px;\n}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A multi-line comment is one comment, not a run of lines to be matched separately - the theme files' explanations all span several
    public function testWarmUpStripsAMultiLineComment(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', "/*\n * Explanation\n * over several lines\n */\n.ui{color:red}");

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            "\n.ui{color:red}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // Two comments on one sheet must not be read as a single one swallowing the rule between them
    public function testWarmUpKeepsWhatSitsBetweenTwoComments(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '/* first */.ui{color:red}/* second */.site{color:blue}');

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '.ui{color:red}.site{color:blue}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A minified sheet puts its first rule right after the license header, and the universal selector makes that "*/*". The "/*" it holds is not a comment opening - reading it as one swallowed SiteBundle's 19 KB down to 95 bytes, and bundles/build/site.css lost every menu rule on every site
    public function testWarmUpKeepsARuleStartingWithTheUniversalSelectorRightAfterAHeader(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '/*! modern-normalize | MIT */*,::before{box-sizing:border-box}.menu{display:flex}');

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '/*! modern-normalize | MIT */*,::before{box-sizing:border-box}.menu{display:flex}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css'),
            'The rule following the header is gone, so every sheet shipping a normalize header loses its whole content.'
        );
    }

    // "/*!" is the convention marking a header that must survive minification - a license, an authorship notice
    public function testWarmUpKeepsALicenseHeader(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', "/*! (c) 975L */\n/* internal */\n.ui{color:red}");

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            "/*! (c) 975L */\n\n.ui{color:red}",
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    public function testWarmUpConcatenatesManagementStylesheetsSeparatelyFromSite(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.ui{color:red}');
        $this->createCssFile('bundles/c975lconfig/css/management.min.css', '.mgmt{color:green}');

        $warmer = $this->createWarmer(
            ['bundles/c975lui/css/styles.min.css'],
            ['bundles/c975lconfig/css/management.min.css']
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '.mgmt{color:green}',
            file_get_contents($this->projectDir . '/public/bundles/build/admin.css')
        );
    }

    // Absolute URLs (CDN resources like cookieconsent.min.css) are skipped, not read from disk
    public function testWarmUpSkipsAbsoluteUrls(): void
    {
        $this->createCssFile('bundles/c975lsite/css/styles.min.css', '.site{color:blue}');

        $warmer = $this->createWarmer(
            ['bundles/c975lsite/css/styles.min.css', 'https://cdnjs.cloudflare.com/lib.css'],
            []
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '.site{color:blue}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    // A contributed stylesheet can be generated at runtime (e.g. ThemeVariablesCssListener) and may not exist yet on a fresh install - it must be skipped, not raise a warning
    public function testWarmUpSkipsAMissingLocalStylesheet(): void
    {
        $this->createCssFile('bundles/c975lsite/css/styles.min.css', '.site{color:blue}');

        $warmer = $this->createWarmer(
            ['bundles/c975lsite/css/styles.min.css', 'bundles/build/site-theme.css'],
            []
        );
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertSame(
            '.site{color:blue}',
            file_get_contents($this->projectDir . '/public/bundles/build/site.css')
        );
    }

    public function testWarmUpCreatesBuildDirectoryWhenMissing(): void
    {
        $warmer = $this->createWarmer([], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $this->assertDirectoryExists($this->projectDir . '/public/bundles/build');
    }

    public function testIsOptionalReturnsTrue(): void
    {
        $warmer = $this->createWarmer([], []);

        $this->assertTrue($warmer->isOptional());
    }

    public function testWarmUpReturnsEmptyArray(): void
    {
        $warmer = $this->createWarmer([], []);

        $this->assertSame([], $warmer->warmUp($this->projectDir . '/var/cache'));
    }

    // No leftover .tmp file after a successful run - write() always renames its temp file over the final path rather than leaving it behind
    public function testWarmUpLeavesNoTemporaryFileBehind(): void
    {
        $this->createCssFile('bundles/c975lui/css/styles.min.css', '.ui{color:red}');

        $warmer = $this->createWarmer(['bundles/c975lui/css/styles.min.css'], []);
        $warmer->warmUp($this->projectDir . '/var/cache');

        $entries = scandir($this->projectDir . '/public/bundles/build');
        $this->assertSame(['admin.css', 'site.css'], array_values(array_diff($entries, ['.', '..'])));
    }

    // A directory that can't be created (blocked by a same-named regular file already sitting at that path, e.g. left over from a broken previous deploy) must fail loudly, not silently no-op
    public function testWarmUpThrowsWhenTheBuildDirectoryCannotBeCreated(): void
    {
        mkdir($this->projectDir . '/public/bundles', 0777, true);
        file_put_contents($this->projectDir . '/public/bundles/build', 'not a directory');

        $warmer = $this->createWarmer([], []);

        $this->expectException(\RuntimeException::class);
        $warmer->warmUp($this->projectDir . '/var/cache');
    }

    // A write failure (permissions, disk full...) must fail loudly, not silently leave a stale/missing file
    public function testWarmUpThrowsWhenTheBuildDirectoryIsNotWritable(): void
    {
        if (0 === posix_getuid()) {
            $this->markTestSkipped('Running as root ignores directory permissions.');
        }

        mkdir($this->projectDir . '/public/bundles/build', 0777, true);
        chmod($this->projectDir . '/public/bundles/build', 0555);

        $warmer = $this->createWarmer([], []);

        try {
            $this->expectException(\RuntimeException::class);
            $warmer->warmUp($this->projectDir . '/var/cache');
        } finally {
            chmod($this->projectDir . '/public/bundles/build', 0775);
        }
    }
}
