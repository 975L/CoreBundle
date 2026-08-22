<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use c975L\UiBundle\Listener\ThemeVariablesCssListener;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class ThemeVariablesCssListenerTest extends TestCase
{
    private string $projectDir;
    private string $cssPath;

    // Sandboxes each test behind its own throwaway project directory, so real filesystem writes can be exercised safely
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/theme-variables-css-listener-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        $this->cssPath = $this->projectDir . '/public/bundles/build/site-theme.css';
    }

    // Leaves no trace of the sandbox project directory once the test finishes
    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

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

    private function config(string $slug, ?string $value, string $group = Config::GROUP_THEME): Config
    {
        return new Config()->setSlug($slug)->setValue($value)->setGroup($group);
    }

    private function createListener(array $themeConfigs): ThemeVariablesCssListener
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findBySlugPrefix')->willReturn($themeConfigs);

        return new ThemeVariablesCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
            new ArrayAdapter(),
        );
    }

    // The per-entity events only flag the compiled files as stale - nothing is written until the flush they belong to completes
    private function flush(ThemeVariablesCssListener $listener): void
    {
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
    }

    public function testPostPersistIgnoresNonThemeConfig(): void
    {
        $listener = $this->createListener([]);

        $args = new PostPersistEventArgs(
            $this->config('site-name', 'My Site', Config::GROUP_GENERAL),
            $this->createStub(EntityManagerInterface::class),
        );
        $listener->postPersist($args);
        $this->flush($listener);

        $this->assertFileDoesNotExist($this->cssPath);
    }

    // A satellite bundle keeps its own colors in its own back-office group: what marks a config as a CSS value is its "theme-" slug, so another group compiles all the same
    public function testAThemeSlugIsCompiledWhateverItsGroup(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-gallery-frame', '#123456', 'gallery'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-gallery-frame', '#123456', 'gallery'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $this->assertStringContainsString('--c975l-color-gallery-frame: #123456;', file_get_contents($this->cssPath));
    }

    public function testPostPersistIgnoresNonConfigEntities(): void
    {
        $listener = $this->createListener([]);

        $args = new PostPersistEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class));
        $listener->postPersist($args);
        $this->flush($listener);

        $this->assertFileDoesNotExist($this->cssPath);
    }

    public function testPostPersistRegeneratesFileWithCssCustomProperties(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', '#ff0000'),
            $this->config('theme-font-family-title', '"Georgia", serif'),
        ]);

        $args = new PostPersistEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        );
        $listener->postPersist($args);
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-color-primary: #ff0000;', $css);
        $this->assertStringContainsString('--c975l-font-family-title: "Georgia", serif;', $css);
    }

    // A pale brand colour used to reach the visitor as a white label on light blue - 1.97:1, and the first thing an accessibility audit reports
    public function testALightThemeColourIsGivenADarkInk(): void
    {
        $listener = $this->createListener([$this->config('theme-color-primary', '#7cc0f0')]);
        $this->flush($this->markStale($listener, 'theme-color-primary', '#7cc0f0'));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-button-color: #000;', $css);
        $this->assertStringContainsString('--c975l-button-link-color: #000;', $css);
        $this->assertStringContainsString('--c975l-button-icon-invert: 0;', $css);
    }

    // The icon goes with the label and never apart from it: an <img> takes no colour of its own, so it is turned over by an inversion
    public function testADarkThemeColourKeepsTheWhiteInkAndTheInvertedIcon(): void
    {
        $listener = $this->createListener([$this->config('theme-color-primary', 'rgb(11, 55, 178)')]);
        $this->flush($this->markStale($listener, 'theme-color-primary', 'rgb(11, 55, 178)'));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-button-color: #fff;', $css);
        $this->assertStringContainsString('--c975l-button-icon-invert: 1;', $css);
    }

    // The secondary carries its own pair, the two colours being read one by one
    public function testTheSecondaryColourIsReadOnItsOwn(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', '#7cc0f0'),
            $this->config('theme-color-secondary', '#0a2d6b'),
        ]);
        $this->flush($this->markStale($listener, 'theme-color-secondary', '#0a2d6b'));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-button-color: #000;', $css);
        $this->assertStringContainsString('--c975l-button-secondary-color: #fff;', $css);
        $this->assertStringContainsString('--c975l-button-secondary-icon-invert: 1;', $css);
    }

    // A colour this does not read is left to the stylesheet's own default rather than guessed at
    public function testAColourThatCannotBeReadDerivesNothing(): void
    {
        $listener = $this->createListener([$this->config('theme-color-primary', 'hsl(200, 80%, 71%)')]);
        $this->flush($this->markStale($listener, 'theme-color-primary', 'hsl(200, 80%, 71%)'));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-color-primary: hsl(200, 80%, 71%);', $css);
        $this->assertStringNotContainsString('--c975l-button-color:', $css);
        $this->assertStringNotContainsString('--c975l-button-icon-invert:', $css);
    }

    // Marks the compiled file stale the way a saved config does
    private function markStale(ThemeVariablesCssListener $listener, string $slug, ?string $value): ThemeVariablesCssListener
    {
        $listener->postPersist(new PostPersistEventArgs($this->config($slug, $value), $this->createStub(EntityManagerInterface::class)));

        return $listener;
    }

    // A bare font name is quoted and gets its generic fallback appended, so the browser has somewhere to go
    public function testRegenerateAppendsGenericFallbackToABareCustomFontName(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-title', 'Roboto'),
            $this->config('theme-font-family-accent', 'Fira Code'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-title', 'Roboto'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-title: "Roboto", sans-serif;', $css);
        $this->assertStringContainsString('--c975l-font-family-accent: "Fira Code", monospace;', $css);
    }

    // An uploaded family whose name carries a digit is only valid CSS once quoted - unquoted, "400" is a number token, not a <custom-ident>, and every font-family reading the variable is dropped
    public function testRegenerateQuotesAFontNameHoldingADigit(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-body', 'cormorant garamond latin 400'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-body', 'cormorant garamond latin 400'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-body: "cormorant garamond latin 400", sans-serif;', $css);
    }

    // An admin who typed the quotes himself gets the same declaration as one who typed the bare name - requoted as it stands, "Roboto" would be declared as "\"Roboto\"", a family matching nothing
    public function testRegenerateDoesNotRequoteAnAlreadyQuotedFontName(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-body', '"Roboto"'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-body', '"Roboto"'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-body: "Roboto", sans-serif;', $css);
    }

    // Same for the single quotes a CSS-aware admin may reach for
    public function testRegenerateDoesNotRequoteASingleQuotedFontName(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-body', "'Open Sans'"),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-body', "'Open Sans'"),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-body: "Open Sans", sans-serif;', $css);
    }

    // A value already picked as one of Config::GENERIC_FONT_FAMILIES never needs a fallback suffix appended to itself
    public function testRegenerateDoesNotAppendFallbackWhenValueIsAlreadyAGeneric(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-body', 'sans-serif'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-body', 'sans-serif'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-body: sans-serif;', $css);
        $this->assertStringNotContainsString('sans-serif, sans-serif', $css);
    }

    // A value already holding a comma is a stack typed by hand, left untouched rather than doubled
    public function testRegenerateDoesNotAppendFallbackWhenValueAlreadyHasOne(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-title', '"Georgia", serif'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-title', '"Georgia", serif'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-title: "Georgia", serif;', $css);
    }

    // Empty/null values are skipped, so the SCSS fallback default keeps applying instead of an empty custom property value
    public function testRegenerateSkipsEmptyAndNullValues(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', null),
            $this->config('theme-color-secondary', ''),
            $this->config('theme-color-background', '#fff'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-background', '#fff'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('--c975l-color-primary', $css);
        $this->assertStringNotContainsString('--c975l-color-secondary', $css);
        $this->assertStringContainsString('--c975l-color-background: #fff;', $css);
    }

    // theme-mode drives the server-side data-theme attribute (layout.html.twig), not a CSS value
    public function testRegenerateExcludesThemeModeSlug(): void
    {
        $listener = $this->createListener([
            $this->config('theme-mode', 'dark'),
            $this->config('theme-color-primary', '#ff0000'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('theme-mode', $css);
        $this->assertStringNotContainsString('--c975l-mode', $css);
    }

    // The "theme-" prefix marks a config as a CSS value; without it, no variable may be emitted
    public function testRegenerateSkipsANonPrefixedSlug(): void
    {
        $listener = $this->createListener([
            $this->config('site-fonts-face-file', '/assets/styles/_fonts.css'),
            $this->config('theme-color-primary', '#ff0000'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('site-fonts-face-file', $css);
        $this->assertStringContainsString('--c975l-color-primary: #ff0000;', $css);
    }

    public function testPostRemoveRegeneratesFileReflectingTheRemainingConfigs(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-secondary', '#00ff00'),
        ]);

        $listener->postRemove(new PostRemoveEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('--c975l-color-primary', $css);
        $this->assertStringContainsString('--c975l-color-secondary: #00ff00;', $css);
    }

    public function testRegenerateCreatesTheBuildDirectoryWhenMissing(): void
    {
        $listener = $this->createListener([]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $this->assertDirectoryExists($this->projectDir . '/public/bundles/build');
    }

    public function testRegenerateWritesAnEmptyFileWhenNoThemeValueIsSet(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', null),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-color-primary', null),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);

        $this->assertSame('', file_get_contents($this->cssPath));
    }

    // Guards against configs persisted before this listener existed (or restored from a backup) that never fire another Doctrine event on their own - cache:warmup/cache:clear must still produce an up-to-date file
    public function testWarmUpRegeneratesFileFromCurrentConfigs(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', '#ff0000'),
        ]);

        $result = $listener->warmUp($this->projectDir);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-color-primary: #ff0000;', $css);
        $this->assertSame([], $result);
    }

    // cache:clear runs as part of every composer install, so on a first install "site_config" does not exist yet - an unguarded query there failed the deploy before the app's own migration could ever run
    public function testWarmUpSurvivesAMissingTable(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findBySlugPrefix')->willThrowException(
            new TableNotFoundException($this->createStub(DriverException::class), null)
        );
        $listener = new ThemeVariablesCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
            new ArrayAdapter(),
        );

        $this->assertSame([], $listener->warmUp($this->projectDir));
    }

    // Regression test: in prod, the real site links UiBundle's concatenated bundles/build/site.css, not site-theme.css directly - without this call, applying a preset would regenerate site-theme.css but the live site would keep serving the stale site.css until the next warmup
    public function testRegenerateRecompilesTheConcatenatedStylesheet(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findBySlugPrefix')->willReturn([$this->config('theme-color-primary', '#ff0000')]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new ThemeVariablesCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);
    }

    // The back-office saves the whole theme group in one flush: site-theme.css + site.css + admin.css used to be rebuilt once per row, ten times over, inside the transaction
    public function testTheWholeThemeGroupSavedAtOnceRecompilesOnlyOnce(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findBySlugPrefix')->willReturn([$this->config('theme-color-primary', '#ff0000')]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new ThemeVariablesCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        foreach (['theme-color-primary', 'theme-color-secondary', 'theme-font-family-title'] as $slug) {
            $listener->postUpdate(new PostUpdateEventArgs(
                $this->config($slug, '#ff0000'),
                $this->createStub(EntityManagerInterface::class),
            ));
        }
        $this->flush($listener);
    }

    // The flag is consumed, so a later flush carrying no theme config recompiles nothing
    public function testTheStaleFlagIsResetAfterTheFlush(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findBySlugPrefix')->willReturn([$this->config('theme-color-primary', '#ff0000')]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new ThemeVariablesCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $this->flush($listener);
        $this->flush($listener);
    }

    public function testIsOptional(): void
    {
        $listener = $this->createListener([]);

        $this->assertTrue($listener->isOptional());
    }
}
