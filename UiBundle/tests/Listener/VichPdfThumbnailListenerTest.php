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
use Symfony\Component\Filesystem\Filesystem;
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
        new Filesystem()->remove($this->projectDir);
    }

    private function createMapping(): PropertyMapping
    {
        $mapping = new PropertyMapping('file', 'filename');
        $mapping->setMapping(['upload_destination' => $this->projectDir . '/public', 'uri_prefix' => '']);

        return $mapping;
    }

    // Regression: the namer keeps the extension as the browser sends it, and a case-sensitive replace left a ".PDF" thumbnail path equal to the document's own
    public function testToWebpPathIgnoresTheCaseOfTheExtension(): void
    {
        $this->assertSame('medias/Rapport.webp', VichPdfThumbnailListener::toWebpPath('medias/Rapport.PDF'));
        $this->assertSame('medias/pdf.files/doc.webp', VichPdfThumbnailListener::toWebpPath('medias/pdf.files/doc.pdf'));
    }

    // Regression: a family tree of 90 x 170 cm rendered at a fixed 300 dpi made a 200 Mpx PNG, and GD ran out of memory on it - the whole page save answering 500
    public function testResolutionKeepsAPosterSizedPageUnderTheMemoryBudget(): void
    {
        $resolution = VichPdfThumbnailListener::resolution(2525.67, 4762.2, 800);

        $this->assertLessThanOrEqual(25_000_000, (2525.67 * $resolution / 72) * (4762.2 * $resolution / 72));
        $this->assertGreaterThanOrEqual(800, 2525.67 * $resolution / 72);
    }

    // An A4 page gets twice the thumbnail's width, a small page stops at 300 dpi rather than being blown up
    public function testResolutionTargetsTwiceTheThumbnailWidthUpTo300Dpi(): void
    {
        $this->assertSame(193, VichPdfThumbnailListener::resolution(595.276, 841.89, 800));
        $this->assertSame(300, VichPdfThumbnailListener::resolution(100, 100, 800));
    }

    // The real chain, Ghostscript and pdfinfo included, on a poster-sized page: the thumbnail is made at its width instead of the save running out of memory
    public function testOnPostUploadMakesTheThumbnailOfAPosterSizedPage(): void
    {
        exec('command -v gs && command -v pdfinfo', $output, $returnVar);
        if (0 !== $returnVar) {
            $this->markTestSkipped('Ghostscript and pdfinfo are needed.');
        }

        $pdfPath = $this->projectDir . '/public/tree.pdf';
        file_put_contents($pdfPath, $this->pdfOfSize(2525.67, 4762.2));

        $media = new Media();
        $media->setFilename('tree.pdf');
        $media->setFile(new File($pdfPath));

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichPdfThumbnailListener($parameterBag);
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertFileExists($this->projectDir . '/public/tree.webp');
        $this->assertSame($media->getImageWidth(), getimagesize($this->projectDir . '/public/tree.webp')[0]);
    }

    // A one-page PDF with an empty page of the given size in points, its cross-reference table computed so no tool has to repair it
    private function pdfOfSize(float $width, float $height): string
    {
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %s %s] >>', $width, $height),
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $index => $object) {
            $offsets[] = \strlen($pdf);
            $pdf .= ($index + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = \strlen($pdf);
        $pdf .= "xref\n0 4\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf . "trailer\n<< /Size 4 /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
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

    // A document reserved to members has already left public/ when this runs (VichImageResizeListener goes first): its thumbnail is made next to it, never under public/ where its first page would be readable by anyone. The imported thumbnail stands for the generation, with no Ghostscript needed
    public function testOnPostUploadKeepsTheThumbnailOfADocumentReservedToMembersNextToIt(): void
    {
        mkdir($this->projectDir . '/private', 0777, true);
        $pdfPath = $this->projectDir . '/private/doc.pdf';
        file_put_contents($pdfPath, '%PDF-1.4');

        $importedThumbnailPath = $this->projectDir . '/imported-thumbnail.webp';
        file_put_contents($importedThumbnailPath, 'fake-webp-bytes');

        $media = new Media();
        $media->setFilename('doc.pdf');
        $media->setFile(new File($pdfPath));
        $media->setImportedThumbnailPath($importedThumbnailPath);
        $media->setMembersOnly(true);

        $parameterBag = $this->createStub(ParameterBagInterface::class);
        $parameterBag->method('get')->willReturn($this->projectDir);

        $listener = new VichPdfThumbnailListener($parameterBag);
        $listener->onPostUpload(new Event($media, $this->createMapping()));

        $this->assertFileExists($this->projectDir . '/private/doc.webp');
        $this->assertFileDoesNotExist($this->projectDir . '/public/doc.webp');
    }
}
