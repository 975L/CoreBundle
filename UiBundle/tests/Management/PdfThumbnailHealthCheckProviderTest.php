<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\EnvironmentProbe;
use c975L\UiBundle\Contract\PdfDocumentSourceInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\PdfThumbnailHealthCheckProvider;
use c975L\UiBundle\Registry\PdfDocumentRegistry;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Translation\TranslatorInterface;

class PdfThumbnailHealthCheckProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/pdf-thumbnail-health-check-test-' . uniqid();
        new Filesystem()->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->projectDir);
    }

    /**
     * @param array<string, bool>                                            $medias    filename => whether its .webp sits next to it on disk
     * @param array<string, bool>                                            $declared  same, for the documents a satellite bundle hands over rather than this bundle's own library
     * @param list<array{filename: string, label: string, editUrl: ?string}> $documents rows handed over as they stand, for what the map above cannot say - a nameless document, a screen that does not exist
     */
    private function createProvider(array $medias, bool $canExec = true, ?string $ghostscript = 'GPL Ghostscript 10.02.1', array $declared = [], array $documents = []): PdfThumbnailHealthCheckProvider
    {
        $rows = [];
        foreach ($medias as $filename => $hasThumbnail) {
            if ($hasThumbnail) {
                file_put_contents($this->projectDir . '/public/' . str_replace('.pdf', '.webp', $filename), 'webp');
            }

            $rows[] = new Media()->setFilename($filename);
        }

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findPdfs')->willReturn($rows);

        foreach ($declared as $filename => $hasThumbnail) {
            if ($hasThumbnail) {
                file_put_contents($this->projectDir . '/public/' . str_replace('.pdf', '.webp', $filename), 'webp');
            }

            $documents[] = ['filename' => $filename, 'label' => 'Declared ' . $filename, 'editUrl' => '/management/book/1'];
        }

        $source = $this->createStub(PdfDocumentSourceInterface::class);
        $source->method('getPdfDocuments')->willReturn($documents);

        $registry = new PdfDocumentRegistry();
        $registry->addProvider($source);

        $environmentProbe = $this->createStub(EnvironmentProbe::class);
        $environmentProbe->method('getSapi')->willReturn('cli');
        $environmentProbe->method('canExec')->willReturn($canExec);
        $environmentProbe->method('getBinaryVersion')->willReturn($canExec ? $ghostscript : null);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/management/media');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $params = []) => $id . '|' . implode('', $params)
        );

        return new PdfThumbnailHealthCheckProvider(
            $mediaRepository,
            $registry,
            $environmentProbe,
            $configService,
            $adminUrlGenerator,
            $translator,
            $this->projectDir,
        );
    }

    public function testGetKind(): void
    {
        $this->assertSame('pdf-thumbnail', $this->createProvider([])->getKind());
    }

    // Whether the server could make a thumbnail is not a defect until something needs one
    public function testASiteWithNoPdfReportsNothing(): void
    {
        $this->assertSame([], $this->createProvider([])->runChecks());
    }

    // No thumbnail is made for a document reserved to members on purpose: its absence is no warning to act on
    public function testADocumentReservedToMembersIsNotReportedForItsMissingThumbnail(): void
    {
        $media = new Media()->setFilename('tree.pdf')->setMembersOnly(true);

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findPdfs')->willReturn([$media]);

        $environmentProbe = $this->createStub(EnvironmentProbe::class);
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');

        $provider = new PdfThumbnailHealthCheckProvider(
            $mediaRepository,
            new PdfDocumentRegistry(),
            $environmentProbe,
            $configService,
            $this->createStub(AdminUrlGeneratorInterface::class),
            $this->createStub(TranslatorInterface::class),
            $this->projectDir,
        );

        $this->assertSame([], $provider->runChecks());
    }

    // The OK row is what lets a fixed media go back to green: results are kept per url and kind, so dropping it would leave the old warning standing forever
    public function testAPdfWithItsThumbnailStillGetsItsRow(): void
    {
        $rows = $this->createProvider(['cv.pdf' => true])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame('https://example.com/cv.pdf', $rows[0]['url']);
    }

    // The case this check exists for: the generation is silent when it fails, so the missing file is the only trace left
    public function testAPdfWithoutItsThumbnailIsAWarning(): void
    {
        $rows = $this->createProvider(['article.pdf' => false])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertStringContainsString('label.health_check_pdf_thumbnail_missing', $rows[0]['summary']);
        $this->assertSame('article.webp', $rows[0]['details']['thumbnail']);
    }

    // "Re-save it" and "no document on this site will ever get one" are the same missing file and two entirely different things to do about it
    public function testAServerThatCannotGenerateSaysSoRatherThanAskingForARetry(): void
    {
        $rows = $this->createProvider(['article.pdf' => false], canExec: false)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertStringContainsString('label.health_check_pdf_thumbnail_unavailable', $rows[0]['summary']);
        $this->assertFalse($rows[0]['details']['exec']);
        $this->assertNull($rows[0]['details']['ghostscript']);
    }

    // exec() reachable but the binary absent lands on the same row: the listener needs both, and neither is fixed by re-saving
    public function testGhostscriptMissingIsReportedAsUnavailableToo(): void
    {
        $rows = $this->createProvider(['article.pdf' => false], ghostscript: null)->runChecks();

        $this->assertStringContainsString('label.health_check_pdf_thumbnail_unavailable', $rows[0]['summary']);
        $this->assertTrue($rows[0]['details']['exec']);
    }

    // A fixture or placeholder media has no file a visitor could ever reach, so it has no thumbnail to miss
    public function testAMediaWithNoFilenameIsSkipped(): void
    {
        $rows = $this->createProvider(['' => false, 'kept.pdf' => true])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame('https://example.com/kept.pdf', $rows[0]['url']);
    }

    // A satellite bundle holding its documents in a table of its own is invisible to the library, and this check is the only thing that would ever say its thumbnails went missing
    public function testADeclaredDocumentWithoutItsThumbnailIsAWarningToo(): void
    {
        $rows = $this->createProvider([], declared: ['medias/book/books/presse.pdf' => false])->runChecks();

        $this->assertCount(1, $rows);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $rows[0]['status']);
        $this->assertSame('https://example.com/medias/book/books/presse.pdf', $rows[0]['url']);
        $this->assertSame('medias/book/books/presse.webp', $rows[0]['details']['thumbnail']);
    }

    // The declaring bundle names the document and the screen it is edited on, this one knowing neither the entity nor the controller behind it
    public function testADeclaredDocumentCarriesItsOwnLabelAndEditScreen(): void
    {
        $rows = $this->createProvider([], declared: ['presse.pdf' => true])->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $rows[0]['status']);
        $this->assertSame('Declared presse.pdf', $rows[0]['label']);
        $this->assertSame('/management/book/1', $rows[0]['editUrl']);
    }

    // A site whose only PDFs are declared elsewhere is still a site with PDFs: reporting nothing here is what let a whole catalog lose its thumbnails unnoticed
    public function testDeclaredDocumentsAloneAreEnoughToReport(): void
    {
        $rows = $this->createProvider([], declared: ['presse.pdf' => true])->runChecks();

        $this->assertNotSame([], $rows);
    }

    // A source is free to hand over a document nothing names, the dashboard then reading the file itself rather than an empty cell
    public function testADeclaredDocumentWithoutALabelFallsBackOnItsFilename(): void
    {
        $rows = $this->createProvider([], documents: [['filename' => 'presse.pdf', 'label' => '', 'editUrl' => null]])->runChecks();

        $this->assertSame('presse.pdf', $rows[0]['label']);
        $this->assertNull($rows[0]['editUrl']);
    }

    // Ghostscript out of reach makes every declared document unfixable too: re-saving it would achieve nothing, and the row has to say so
    public function testADeclaredDocumentIsReportedUnavailableWhenTheServerCannotGenerate(): void
    {
        $rows = $this->createProvider([], canExec: false, declared: ['presse.pdf' => false])->runChecks();

        $this->assertSame('label.health_check_pdf_thumbnail_unavailable|presse.pdf', $rows[0]['summary']);
        $this->assertFalse($rows[0]['details']['exec']);
    }

    // Both halves land in the same run, the dashboard holding one list per kind
    public function testTheLibraryAndTheDeclaredDocumentsAreReportedTogether(): void
    {
        $rows = $this->createProvider(['cv.pdf' => true], declared: ['presse.pdf' => true])->runChecks();

        $this->assertCount(2, $rows);
        $this->assertSame(['https://example.com/cv.pdf', 'https://example.com/presse.pdf'], array_column($rows, 'url'));
    }
}
