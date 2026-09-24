<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Twig\ImageSizeExtension;
use PHPUnit\Framework\TestCase;

class ImageSizeExtensionTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-image-size-test-' . uniqid();
        mkdir($this->projectDir . '/public/medias', 0775, true);
        imagepng(imagecreatetruecolor(30, 40), $this->projectDir . '/public/medias/cover.png');
    }

    protected function tearDown(): void
    {
        unlink($this->projectDir . '/public/medias/cover.png');
        rmdir($this->projectDir . '/public/medias');
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    private function createExtension(): ImageSizeExtension
    {
        return new ImageSizeExtension(new ImageDimensionsReader(), $this->projectDir);
    }

    // The path asset() writes, leading slash and version query included, reads the file under public/
    public function testReadsSizeOfAssetPath(): void
    {
        $this->assertSame(['width' => 30, 'height' => 40], $this->createExtension()->imageSize('/medias/cover.png?v=123'));
    }

    // A path with no leading slash reads the same file
    public function testReadsSizeOfRelativePath(): void
    {
        $this->assertSame(['width' => 30, 'height' => 40], $this->createExtension()->imageSize('medias/cover.png'));
    }

    // A remote url, a missing file, an empty src and a path climbing out of public/ give no size
    public function testReturnsNullWhenNothingToMeasure(): void
    {
        $extension = $this->createExtension();

        $this->assertNull($extension->imageSize('https://example.com/medias/cover.png'));
        $this->assertNull($extension->imageSize('/medias/missing.png'));
        $this->assertNull($extension->imageSize(''));
        $this->assertNull($extension->imageSize(null));
        $this->assertNull($extension->imageSize('/../public/medias/cover.png'));
    }
}
