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

        $listener = new VichImageResizeListener($parameterBag);
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertSame('not-a-gd-decodable-ico', file_get_contents($icoPath));
    }
}
