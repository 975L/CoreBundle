<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\AppScreenshotsCommand;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\SiteGraphicImportProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

// The screenshots the Films workshop sends, handed to the site graphics import as one pool
class AppScreenshotsCommandTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/app-screenshots-test-' . uniqid();
        new Filesystem()->mkdir($this->folder);
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->folder);
    }

    // Images only, in their name order, each one carrying the screenshot role
    public function testTheImagesOfTheFolderReplaceThePool(): void
    {
        foreach (['2-lecteur.webp', '1-accueil.webp', 'notes.txt'] as $file) {
            touch($this->folder . '/' . $file);
        }

        $importProvider = $this->createMock(SiteGraphicImportProvider::class);
        $importProvider->expects($this->once())->method('import')->with([
            ['role' => Media::ROLE_APP_SCREENSHOT, 'file' => '1-accueil.webp'],
            ['role' => Media::ROLE_APP_SCREENSHOT, 'file' => '2-lecteur.webp'],
        ], $this->folder)->willReturn(['created' => 2, 'updated' => 0]);

        $tester = new CommandTester(new AppScreenshotsCommand($importProvider));

        $this->assertSame(Command::SUCCESS, $tester->execute(['folder' => $this->folder . '/']));
        $this->assertStringContainsString('2 capture(s)', $tester->getDisplay());
    }

    // Nothing to import keeps the current screenshots rather than emptying the pool
    public function testAnEmptyFolderLeavesTheScreenshotsInPlace(): void
    {
        $importProvider = $this->createMock(SiteGraphicImportProvider::class);
        $importProvider->expects($this->never())->method('import');

        $tester = new CommandTester(new AppScreenshotsCommand($importProvider));

        $this->assertSame(Command::FAILURE, $tester->execute(['folder' => $this->folder . '/missing']));
    }
}
