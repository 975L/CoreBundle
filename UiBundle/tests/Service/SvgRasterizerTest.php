<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Service\SvgRasterizer;
use PHPUnit\Framework\TestCase;

class SvgRasterizerTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/svg-rasterizer-test-' . uniqid();
        mkdir($this->directory, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*'));
        rmdir($this->directory);
    }

    private function writeFile(string $name, string $content): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    private function skipWithoutImagick(): void
    {
        if (!SvgRasterizer::isSupported()) {
            $this->markTestSkipped('ext-imagick with SVG support is needed to rasterize an SVG.');
        }
    }

    // The whole point: a vector upload comes back out as pixels the GD pipeline can then crop and convert
    public function testRasterizeInPlaceTurnsAnSvgIntoAPng(): void
    {
        $this->skipWithoutImagick();

        $path = $this->writeFile('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="#e63946"/></svg>');

        $this->assertTrue((new SvgRasterizer())->rasterizeInPlace($path));

        $dimensions = getimagesize($path);
        $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
        $this->assertGreaterThanOrEqual(114, $dimensions[0]);
    }

    // A tiny declared size must not cap the rendering: the icon pipeline downscales from here, and rasterizing at 16px would hand it a blurry source
    public function testRasterizeInPlaceRendersWellAboveTheDeclaredSize(): void
    {
        $this->skipWithoutImagick();

        $path = $this->writeFile('tiny.svg', '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16"><rect width="16" height="16" fill="#457b9d"/></svg>');

        (new SvgRasterizer())->rasterizeInPlace($path);

        $this->assertGreaterThanOrEqual(256, getimagesize($path)[0]);
    }

    // Regression: the size is read off the root tag alone. Matching the first width= anywhere in the markup picked up a child shape's own width instead, which both sized the rendering on that shape and hid the "declares no size at all" case ImageMagick refuses to read
    public function testRasterizeInPlaceIgnoresTheWidthOfAChildElement(): void
    {
        $this->skipWithoutImagick();

        $path = $this->writeFile('nosize.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect x="0" y="0" width="50" height="50" fill="#264653"/></svg>');

        $this->assertTrue((new SvgRasterizer())->rasterizeInPlace($path));
        $this->assertSame(IMAGETYPE_PNG, getimagesize($path)[2]);
    }

    // Regression: `stroke-width` sits on the root tag of about every icon-set SVG and matched the width lookup, sizing the whole rendering on a line thickness. The density then computed lands far beyond ImageMagick's resource limits, which refuses the document outright and has a perfectly valid SVG turned down at validation
    public function testRasterizeInPlaceIgnoresAStrokeWidthOnTheRootTag(): void
    {
        $this->skipWithoutImagick();

        $path = $this->writeFile('stroked.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" fill="none" stroke="#2a9d8f" stroke-width="1"><circle cx="256" cy="256" r="200"/></svg>');

        $this->assertTrue((new SvgRasterizer())->rasterizeInPlace($path));
        $this->assertSame(IMAGETYPE_PNG, getimagesize($path)[2]);
    }

    // An editor's xml declaration, doctype and comment header all sit before the root tag, which still has to be found behind them
    public function testRasterizeInPlaceReadsARootTagBehindAnXmlHeader(): void
    {
        $this->skipWithoutImagick();

        $path = $this->writeFile('exported.svg', "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<!-- Generator: Adobe Illustrator -->\n<svg xmlns=\"http://www.w3.org/2000/svg\" x=\"0px\" y=\"0px\" width=\"64px\" height=\"64px\" viewBox=\"0 0 64 64\"><circle cx=\"32\" cy=\"32\" r=\"30\" fill=\"#f4a261\"/></svg>");

        $this->assertTrue((new SvgRasterizer())->rasterizeInPlace($path));
        $this->assertGreaterThanOrEqual(256, getimagesize($path)[0]);
    }

    // Handed any other upload, it must keep its hands off - the caller passes every fixed-icon upload through it
    public function testRasterizeInPlaceLeavesANonSvgFileUntouched(): void
    {
        $path = $this->writeFile('favicon.ico', 'not-an-svg-at-all');

        $this->assertFalse((new SvgRasterizer())->rasterizeInPlace($path));
        $this->assertSame('not-an-svg-at-all', file_get_contents($path));
    }

    // Malformed markup must fail as "nothing rasterized" rather than take the upload down
    public function testRasterizeInPlaceLeavesABrokenSvgUntouched(): void
    {
        $this->skipWithoutImagick();

        $broken = '<svg xmlns="http://www.w3.org/2000/svg"><circle cx="50"';
        $path = $this->writeFile('broken.svg', $broken);

        $this->assertFalse((new SvgRasterizer())->rasterizeInPlace($path));
        $this->assertSame($broken, file_get_contents($path));
    }

    // Without the extension nothing can be rasterized, and the caller has to keep refusing SVG uploads for icon roles (see FixedIconFormatValidator)
    public function testIsSupportedFollowsTheImagickExtension(): void
    {
        if (!extension_loaded('imagick')) {
            $this->assertFalse(SvgRasterizer::isSupported());

            return;
        }

        $this->assertSame([] !== \Imagick::queryFormats('SVG'), SvgRasterizer::isSupported());
    }
}
