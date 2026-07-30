<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\MediaDimensionsFiller;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class MediaDimensionsFillerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/media-dimensions-filler-test-' . uniqid();
        (new Filesystem())->mkdir($this->projectDir . '/public/medias');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    private function createFiller(): MediaDimensionsFiller
    {
        return new MediaDimensionsFiller(new ImageDimensionsReader(), $this->projectDir);
    }

    private function createMedia(string $filename, int $width, int $height): Media
    {
        imagepng(imagecreatetruecolor($width, $height), $this->projectDir . '/public/' . $filename);

        $media = new Media();
        $media->setFilename($filename);

        return $media;
    }

    public function testFillIfBlankReadsTheStoredFileWhenBothDimensionsAreMissing(): void
    {
        $media = $this->createMedia('medias/photo.png', 800, 600);

        $this->createFiller()->fillIfBlank($media);

        $this->assertSame('800', $media->getWidth());
        $this->assertSame('600', $media->getHeight());
    }

    // An empty string is what an untouched text input submits, and is the very state this guards against - treated exactly like null
    public function testFillIfBlankTreatsEmptyStringsAsMissing(): void
    {
        $media = $this->createMedia('medias/photo.png', 800, 600);
        $media->setWidth('');
        $media->setHeight('');

        $this->createFiller()->fillIfBlank($media);

        $this->assertSame('800', $media->getWidth());
    }

    // A width alone (height left blank to keep the ratio) is a deliberate pair typed by the admin - filling the height from the file would stretch the image
    public function testFillIfBlankLeavesAMediaWithASingleAdminTypedDimensionAlone(): void
    {
        $media = $this->createMedia('medias/photo.png', 800, 600);
        $media->setWidth('300');

        $this->createFiller()->fillIfBlank($media);

        $this->assertSame('300', $media->getWidth());
        $this->assertNull($media->getHeight());
    }

    // A media whose file has no readable dimensions (pdf, audio, video) or isn't stored yet keeps its empty values rather than failing the save
    public function testFillIfBlankLeavesAMediaWithNoReadableFileUntouched(): void
    {
        $media = new Media();
        $media->setFilename('medias/document.pdf');

        $this->createFiller()->fillIfBlank($media);

        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
    }

    public function testFillIfBlankLeavesAMediaWithNoFileAtAllUntouched(): void
    {
        $media = new Media();

        $this->createFiller()->fillIfBlank($media);

        $this->assertNull($media->getWidth());
        $this->assertNull($media->getHeight());
    }
}
