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

    private function createWarmer(array $stylesheets, array $managementStylesheets): StylesheetCacheWarmer
    {
        $registry = $this->createStub(StylesheetRegistry::class);
        $registry->method('all')->willReturn($stylesheets);

        $managementRegistry = $this->createStub(StylesheetManagementRegistry::class);
        $managementRegistry->method('all')->willReturn($managementStylesheets);

        return new StylesheetCacheWarmer($registry, $managementRegistry, $this->projectDir);
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
