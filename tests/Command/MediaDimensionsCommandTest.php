<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Command;

use c975L\UiBundle\Command\MediaDimensionsCommand;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use c975L\UiBundle\Service\ImageDimensionsReader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

class MediaDimensionsCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-dimensions-test-' . uniqid();
        (new Filesystem())->mkdir($this->projectDir . '/public/medias');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createMedia(string $filename): Media
    {
        $media = new Media();
        $media->setFilename($filename);

        return $media;
    }

    private function writePng(string $filename, int $width, int $height): void
    {
        imagepng(imagecreatetruecolor($width, $height), $this->projectDir . '/public/' . $filename);
    }

    private function createTester(array $medias, EntityManagerInterface $entityManager): CommandTester
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findWithoutDimensions')->willReturn($medias);

        return new CommandTester(new MediaDimensionsCommand($entityManager, $repository, new ImageDimensionsReader(), $this->projectDir));
    }

    public function testExecuteFillsDimensionsReadFromTheFile(): void
    {
        $this->writePng('medias/photo.png', 800, 600);
        $media = $this->createMedia('medias/photo.png');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->createTester([$media], $entityManager);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertSame('800', $media->getWidth());
        $this->assertSame('600', $media->getHeight());
        $this->assertStringContainsString('1 média(s) renseigné(s) sur 1', $tester->getDisplay());
    }

    // A pdf/audio/video media has no dimensions to read, and neither has a row whose file has since disappeared - both are reported, not counted as filled, and neither aborts the run
    public function testExecuteSkipsAMediaWithNoReadableDimensions(): void
    {
        $media = $this->createMedia('medias/document.pdf');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $tester = $this->createTester([$media], $entityManager);
        $tester->execute([]);

        $this->assertNull($media->getWidth());
        $this->assertStringContainsString('0 média(s) renseigné(s) sur 1', $tester->getDisplay());
        $this->assertStringContainsString('medias/document.pdf', $tester->getDisplay());
    }

    public function testExecuteFlushesOnceForTheWholeBatch(): void
    {
        $this->writePng('medias/first.png', 100, 50);
        $this->writePng('medias/second.png', 200, 100);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $tester = $this->createTester([$this->createMedia('medias/first.png'), $this->createMedia('medias/second.png')], $entityManager);
        $tester->execute([]);

        $this->assertStringContainsString('2 média(s) renseigné(s) sur 2', $tester->getDisplay());
    }

    public function testExecuteDoesNothingWhenEveryMediaAlreadyHasDimensions(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('flush');

        $tester = $this->createTester([], $entityManager);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('déjà des dimensions', $tester->getDisplay());
    }
}
