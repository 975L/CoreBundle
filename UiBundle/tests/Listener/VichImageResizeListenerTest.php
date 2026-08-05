<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Listener;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Listener\VichImageResizeListener;
use c975L\UiBundle\Service\ImageDimensionsReader;
use c975L\UiBundle\Service\SvgRasterizer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class VichImageResizeListenerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/vich-image-resize-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->projectDir . '/public/*'));
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir);
    }

    private function createMapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->projectDir . '/public', 'uri_prefix' => '']);

        return $mapping;
    }

    // Regression: a sync roundtrip re-feeds an .ico back in, which GD can't decode and used to crash the import
    public function testOnPostUploadDoesNotCrashWhenFixedIconFileIsAlreadyInTargetFormat(): void
    {
        $icoPath = $this->projectDir . '/public/favicon.ico';
        file_put_contents($icoPath, 'not-a-gd-decodable-ico');

        $media = new Media();
        $media->setRole(Media::ROLE_FAVICON);
        $media->setFilename('favicon.ico');
        $media->setFile(new File($icoPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader(), new SvgRasterizer());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('not-a-gd-decodable-ico', file_get_contents($icoPath));
    }

    // Every <img> needs its intrinsic size to reserve its box before the file arrives, so an upload records it - the "img-responsive" class only keeps the image fluid, it can't tell the browser the proportions in advance
    public function testOnPostUploadStoresTheStoredFileDimensions(): void
    {
        $pngPath = $this->projectDir . '/public/photo.png';
        imagepng(imagecreatetruecolor(1200, 800), $pngPath);

        $media = new Media();
        $media->setFilename('photo.png');
        $media->setFile(new File($pngPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader(), new SvgRasterizer());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        // Not the uploaded 1200x800: processImage() downscales to the entity's own getImageWidth() first, and the recorded size has to describe the file actually served
        $this->assertSame((string) $media->getImageWidth(), $media->getWidth());
        $this->assertSame((string) (int) ($media->getImageWidth() * 800 / 1200), $media->getHeight());
    }

    // Regression: an upload narrower than the entity's target width used to be enlarged to it, producing a softer and heavier file out of pixels that were never there - and, on a VichMultiSizeImageInterface entity, a stored "medium" bigger than the "highres" derivative, which has always capped itself at the original
    public function testOnPostUploadNeverEnlargesAnUploadSmallerThanTheTargetWidth(): void
    {
        $pngPath = $this->projectDir . '/public/small.png';
        imagepng(imagecreatetruecolor(300, 200), $pngPath);

        $media = new Media();
        $media->setFilename('small.png');
        $media->setFile(new File($pngPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader(), new SvgRasterizer());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertLessThan($media->getImageWidth(), 300, 'The uploaded file has to be narrower than the target width, or this test checks nothing.');
        $this->assertSame('300', $media->getWidth());
        $this->assertSame('200', $media->getHeight());

        $dimensions = getimagesize($pngPath);
        $this->assertSame(300, $dimensions[0]);
        $this->assertSame(200, $dimensions[1]);
    }

    // Regression: the stored file carries the role's own .ico extension whatever was uploaded (see UiMediaNamer), so deciding on the extension left every raster favicon unconverted - stored as the uploaded png under an .ico name, neither cropped to 48x48 nor wrapped as a real icon
    public function testOnPostUploadConvertsARasterUploadedForTheFaviconRole(): void
    {
        $iconPath = $this->projectDir . '/public/favicon.ico';
        imagepng(imagecreatetruecolor(256, 256), $iconPath);

        $media = new Media();
        $media->setRole(Media::ROLE_FAVICON);
        $media->setFilename('favicon.ico');
        $media->setFile(new File($iconPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader(), new SvgRasterizer());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        // The ICO signature: reserved 0, type 1 (icon), 1 image
        $this->assertSame("\0\0\1\0\1\0", substr((string) file_get_contents($iconPath), 0, 6));

        $dimensions = getimagesize($iconPath);
        $this->assertSame(48, $dimensions[0]);
        $this->assertSame(48, $dimensions[1]);
    }

    // An SVG uploaded as favicon/apple-touch-icon is rasterized upstream, then converted like any other upload - the stored file must be the role's own format, never the SVG the admin picked (see SvgRasterizer)
    public function testOnPostUploadRasterizesAnSvgUploadedForAFixedIconRole(): void
    {
        if (!SvgRasterizer::isSupported()) {
            $this->markTestSkipped('ext-imagick with SVG support is needed to rasterize an SVG.');
        }

        // Named after the role's target format already, the namer having rewritten the extension before the upload landed (see UiMediaNamer)
        $iconPath = $this->projectDir . '/public/apple-touch-icon.png';
        file_put_contents($iconPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="45" fill="#e63946"/></svg>');

        $media = new Media();
        $media->setRole(Media::ROLE_APPLE_TOUCH_ICON);
        $media->setFilename('apple-touch-icon.png');
        $media->setFile(new File($iconPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader(), new SvgRasterizer());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $dimensions = getimagesize($iconPath);
        $this->assertSame(IMAGETYPE_PNG, $dimensions[2]);
        $this->assertSame(114, $dimensions[0]);
        $this->assertSame(114, $dimensions[1]);
    }

    // An svg is never resized (GD can't decode it), but it still has to carry its dimensions
    public function testOnPostUploadStoresDimensionsOfAFileItDoesNotProcess(): void
    {
        $svgPath = $this->projectDir . '/public/logo.svg';
        file_put_contents($svgPath, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 150"></svg>');

        $media = new Media();
        $media->setFilename('logo.svg');
        $media->setFile(new File($svgPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader(), new SvgRasterizer());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('300', $media->getWidth());
        $this->assertSame('150', $media->getHeight());
        $this->assertStringContainsString('<svg', (string) file_get_contents($svgPath));
    }
}
