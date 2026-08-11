<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use c975L\UiBundle\Entity\Font;
use c975L\UiBundle\Listener\FontCssListener;
use c975L\UiBundle\Repository\FontRepository;
use c975L\UiBundle\Twig\FontPreloadExtension;
use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class FontCssListenerTest extends TestCase
{
    private string $projectDir;
    private string $cssPath;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/font-css-listener-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        $this->cssPath = $this->projectDir . '/public/bundles/build/site-fonts-uploaded.css';
    }

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

    private function font(string $name, int $weight, string $style, ?string $filename): Font
    {
        $font = new Font()->setName($name)->setWeight($weight)->setStyle($style);
        $font->setFilename($filename);

        return $font;
    }

    // What Doctrine really raises when the table behind the repository does not exist yet
    private function tableNotFound(): TableNotFoundException
    {
        return new TableNotFoundException($this->createStub(DriverException::class), null);
    }

    private function createListener(array $fonts): FontCssListener
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn($fonts);

        return new FontCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
            new ArrayAdapter(),
        );
    }

    // The <head>'s font preloads are computed from the same rows, so a Font change has to drop them too
    public function testRegeneratingDropsTheFontPreloadCache(): void
    {
        $cache = new ArrayAdapter();
        $cache->get(FontPreloadExtension::CACHE_KEY, static fn (): array => [['path' => 'stale.woff2', 'type' => 'font/woff2']]);

        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);
        $listener = new FontCssListener($repository, $this->createStub(StylesheetCacheWarmer::class), $this->projectDir, $cache);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $this->assertFalse($cache->hasItem(FontPreloadExtension::CACHE_KEY));
    }

    public function testPostPersistIgnoresNonFontEntities(): void
    {
        $listener = $this->createListener([]);

        $listener->postPersist(new PostPersistEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class)));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $this->assertFileDoesNotExist($this->cssPath);
    }

    public function testPostPersistRegeneratesFileWithFontFaceRule(): void
    {
        $listener = $this->createListener([
            $this->font('Roboto', 700, 'italic', 'medias/fonts/font-1-abc.woff2'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 700, 'italic', 'medias/fonts/font-1-abc.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('font-family: "Roboto";', $css);
        $this->assertStringContainsString('src: url("/medias/fonts/font-1-abc.woff2") format("woff2");', $css);
        $this->assertStringContainsString('font-weight: 700;', $css);
        $this->assertStringContainsString('font-style: italic;', $css);
    }

    public function testRegenerateMapsExtensionsToTheirFontFaceFormatToken(): void
    {
        $listener = $this->createListener([
            $this->font('Alpha', 400, 'normal', 'medias/fonts/font-1.ttf'),
            $this->font('Beta', 400, 'normal', 'medias/fonts/font-2.woff'),
            $this->font('Gamma', 400, 'normal', 'medias/fonts/font-3.woff2'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Alpha', 400, 'normal', 'medias/fonts/font-1.ttf'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('format("truetype")', $css);
        $this->assertStringContainsString('format("woff")', $css);
        $this->assertStringContainsString('format("woff2")', $css);
    }

    public function testRegenerateSkipsRowsWithoutFilename(): void
    {
        $listener = $this->createListener([
            $this->font('Roboto', 400, 'normal', null),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 400, 'normal', null),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('Roboto', $css);
    }

    public function testRegenerateEscapesDoubleQuotesInFontName(): void
    {
        $listener = $this->createListener([
            $this->font('My "Font"', 400, 'normal', 'medias/fonts/font-1.woff2'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('My "Font"', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('font-family: "My \\"Font\\"";', $css);
    }

    public function testPostRemoveRegeneratesFileReflectingTheRemainingFonts(): void
    {
        $listener = $this->createListener([
            $this->font('Georgia', 400, 'normal', 'medias/fonts/font-2.woff2'),
        ]);

        $listener->postRemove(new PostRemoveEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('Roboto', $css);
        $this->assertStringContainsString('Georgia', $css);
    }

    public function testRegenerateCreatesTheBuildDirectoryWhenMissing(): void
    {
        $listener = $this->createListener([]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $this->assertDirectoryExists($this->projectDir . '/public/bundles/build');
    }

    public function testRegenerateRecompilesTheConcatenatedStylesheet(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new FontCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        $listener->postUpdate(new PostUpdateEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
    }

    // A bulk import persists its whole batch in one flush - the file is rewritten once, not once per font
    public function testAWholeBatchOnlyRegeneratesOnce(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new FontCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        foreach (range(1, 20) as $i) {
            $listener->postPersist(new PostPersistEventArgs(
                $this->font('Font ' . $i, 400, 'normal', 'medias/fonts/font-' . $i . '.woff2'),
                $this->createStub(EntityManagerInterface::class),
            ));
        }
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
    }

    // A flush carrying no Font at all leaves the stylesheets alone
    public function testPostFlushWithoutAnyFontTouchedRegeneratesNothing(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->never())->method('compileAll');

        $listener = new FontCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        $listener->postPersist(new PostPersistEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class)));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));

        $this->assertFileDoesNotExist($this->cssPath);
    }

    // The flag is dropped once consumed, so a later flush touching nothing does not rewrite the file again
    public function testTheStaleFlagIsResetAfterRegenerating(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new FontCssListener($repository, $stylesheetCacheWarmer, $this->projectDir, new ArrayAdapter());
        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
    }

    public function testIsOptional(): void
    {
        $listener = $this->createListener([]);

        $this->assertTrue($listener->isOptional());
    }

    public function testWarmUpRegeneratesFileFromCurrentFonts(): void
    {
        $listener = $this->createListener([
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
        ]);

        $result = $listener->warmUp($this->projectDir);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('Roboto', $css);
        $this->assertSame([], $result);
    }

    // cache:clear runs as part of every composer install, so on a first install "site_font" does not exist yet - an unguarded query there failed the deploy before the app's own migration could ever run
    public function testWarmUpSurvivesAMissingTable(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willThrowException($this->tableNotFound());
        $listener = new FontCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
            new ArrayAdapter(),
        );

        $this->assertSame([], $listener->warmUp($this->projectDir));
    }

    // A flush is a different matter: the table exists by then, so a query failing there is a real error the caller must see
    public function testAFlushDoesNotSwallowTheSameFailure(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willThrowException($this->tableNotFound());
        $listener = new FontCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
            new ArrayAdapter(),
        );

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $this->expectException(TableNotFoundException::class);
        $listener->postFlush(new PostFlushEventArgs($this->createStub(EntityManagerInterface::class)));
    }
}
