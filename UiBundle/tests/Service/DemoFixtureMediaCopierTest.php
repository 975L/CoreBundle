<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\DemoFixtureMediaCopier;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The file a demo dataset hands VichUploader: a copy of the placeholder, never the placeholder itself, swept once the run is over
class DemoFixtureMediaCopierTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-copier-test-' . uniqid();
        mkdir($this->projectDir . '/public/showcase', 0o777, true);
        file_put_contents($this->projectDir . '/public/showcase/demo.jpg', 'picture');
    }

    // A ReplacingFile Vich does not ignore, pointing at a copy so the placeholder survives the move
    public function testItHandsACopyOfThePlaceholder(): void
    {
        $file = new DemoFixtureMediaCopier($this->projectDir)->copy('showcase/demo.jpg');

        $this->assertInstanceOf(ReplacingFile::class, $file);
        $this->assertNotSame($this->projectDir . '/public/showcase/demo.jpg', $file->getPathname());
        $this->assertSame('picture', file_get_contents($file->getPathname()));
        $this->assertFileExists($this->projectDir . '/public/showcase/demo.jpg');
    }

    // A file the site names and no longer holds gives nothing rather than a row pointing at no file
    public function testAFileThatIsNotThereGivesNothing(): void
    {
        $this->assertNull(new DemoFixtureMediaCopier($this->projectDir)->copy('showcase/absent.jpg'));
    }

    // A copy Vich never moved, the load having failed, is deleted at the end of the run; the placeholder stays
    public function testTheLeftoverCopiesAreSwept(): void
    {
        $copier = new DemoFixtureMediaCopier($this->projectDir);
        $path = $copier->copy('showcase/demo.jpg')->getPathname();

        $copier->removeTemporaryFiles();

        $this->assertFileDoesNotExist($path);
        $this->assertFileExists($this->projectDir . '/public/showcase/demo.jpg');
    }
}
