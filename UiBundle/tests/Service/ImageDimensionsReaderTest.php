<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\ImageDimensionsReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ImageDimensionsReaderTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/image-dimensions-test-' . uniqid();
        (new Filesystem())->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    private function writeSvg(string $attributes): string
    {
        $path = $this->directory . '/logo.svg';
        file_put_contents($path, sprintf('<svg xmlns="http://www.w3.org/2000/svg" %s></svg>', $attributes));

        return $path;
    }

    public function testReadReturnsRasterDimensions(): void
    {
        $path = $this->directory . '/photo.png';
        imagepng(imagecreatetruecolor(640, 480), $path);

        $this->assertSame(['width' => 640, 'height' => 480], (new ImageDimensionsReader())->read($path));
    }

    public function testReadReturnsNullWhenFileDoesNotExist(): void
    {
        $this->assertNull((new ImageDimensionsReader())->read($this->directory . '/missing.png'));
    }

    public function testReadReturnsNullOnANonImageFile(): void
    {
        $path = $this->directory . '/document.pdf';
        file_put_contents($path, 'not-an-image');

        $this->assertNull((new ImageDimensionsReader())->read($path));
    }

    public function testReadReturnsSvgWidthAndHeightAttributes(): void
    {
        $this->assertSame(
            ['width' => 120, 'height' => 60],
            (new ImageDimensionsReader())->read($this->writeSvg('width="120px" height="60"'))
        );
    }

    // The common case for an svg exported by a drawing tool: no width/height at all, everything in the viewBox
    public function testReadFallsBackToTheSvgViewBox(): void
    {
        $this->assertSame(
            ['width' => 300, 'height' => 150],
            (new ImageDimensionsReader())->read($this->writeSvg('viewBox="0 0 300 150"'))
        );
    }

    // "100%" says nothing about a pixel count, so the viewBox has to win over it rather than produce an invalid width="100%" attribute
    public function testReadIgnoresSvgDimensionsGivenInRelativeUnits(): void
    {
        $this->assertSame(
            ['width' => 300, 'height' => 150],
            (new ImageDimensionsReader())->read($this->writeSvg('width="100%" height="100%" viewBox="0 0 300 150"'))
        );
    }

    public function testReadReturnsNullOnAnSvgWithNoUsableSize(): void
    {
        $this->assertNull((new ImageDimensionsReader())->read($this->writeSvg('width="100%"')));
    }

    public function testReadReturnsNullOnAMalformedSvg(): void
    {
        $path = $this->directory . '/broken.svg';
        file_put_contents($path, '<svg width="10"');

        $this->assertNull((new ImageDimensionsReader())->read($path));
    }
}
