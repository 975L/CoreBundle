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

    // Regression test: a content_import "sync all" roundtrip re-feeds a role=favicon Media's already-converted
    // .ico file back in as if it were a fresh upload (see SiteBundle's SiteGraphicExportProvider/SiteGraphicImportProvider).
    // GD can't decode .ico as a source, which used to crash the whole import with
    // Imagine\Exception\RuntimeException("Unable to open image ...") instead of just skipping the reprocessing
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

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader());
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

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        // Not the uploaded 1200x800: processImage() downscales to the entity's own getImageWidth() first, and the recorded size has to describe the file actually served
        $this->assertSame((string) $media->getImageWidth(), $media->getWidth());
        $this->assertSame((string) (int) ($media->getImageWidth() * 800 / 1200), $media->getHeight());
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

        $listener = new VichImageResizeListener($parameterBag, new ImageDimensionsReader());
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('300', $media->getWidth());
        $this->assertSame('150', $media->getHeight());
        $this->assertStringContainsString('<svg', (string) file_get_contents($svgPath));
    }
}
