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
use c975L\UiBundle\Listener\VichPdfThumbnailListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Mapping\PropertyMapping;

class VichPdfThumbnailListenerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/vich-pdf-thumbnail-test-' . uniqid();
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

    // Regression: exec() is disabled on managed hosts, which used to crash the whole import
    public function testOnPostUploadDoesNotCrashWhenExecIsDisabled(): void
    {
        require_once __DIR__ . '/Fixtures/vich_pdf_thumbnail_exec_disabled.php';

        $pdfPath = $this->projectDir . '/public/doc.pdf';
        file_put_contents($pdfPath, '%PDF-1.4');

        $media = new Media();
        $media->setFilename('doc.pdf');
        $media->setFile(new File($pdfPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichPdfThumbnailListener($parameterBag);
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertFileDoesNotExist($this->projectDir . '/public/doc.webp');
    }

    // A sync import sets this before upload, so the archive's thumbnail is reused with no Ghostscript
    public function testOnPostUploadCopiesTheImportedThumbnailInsteadOfGeneratingOne(): void
    {
        $pdfPath = $this->projectDir . '/public/doc.pdf';
        file_put_contents($pdfPath, '%PDF-1.4');

        $importedThumbnailPath = $this->projectDir . '/imported-thumbnail.webp';
        file_put_contents($importedThumbnailPath, 'fake-webp-bytes');

        $media = new Media();
        $media->setFilename('doc.pdf');
        $media->setFile(new File($pdfPath));
        $media->setImportedThumbnailPath($importedThumbnailPath);

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichPdfThumbnailListener($parameterBag);
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertFileExists($this->projectDir . '/public/doc.webp');
        $this->assertSame('fake-webp-bytes', file_get_contents($this->projectDir . '/public/doc.webp'));

        unlink($importedThumbnailPath);
        unlink($this->projectDir . '/public/doc.webp');
    }

    public function testOnPostUploadDoesNothingWhenTheImportedThumbnailPathDoesNotExist(): void
    {
        $pdfPath = $this->projectDir . '/public/doc.pdf';
        file_put_contents($pdfPath, '%PDF-1.4');

        $media = new Media();
        $media->setFilename('doc.pdf');
        $media->setFile(new File($pdfPath));
        $media->setImportedThumbnailPath($this->projectDir . '/missing-thumbnail.webp');

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichPdfThumbnailListener($parameterBag);
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertFileDoesNotExist($this->projectDir . '/public/doc.webp');
    }
}
