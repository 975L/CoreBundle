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
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\PdfThumbnailHealthCheckProvider;
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
     * @param array<string, bool> $medias filename => whether its .webp sits next to it on disk
     */
    private function createProvider(array $medias, bool $canExec = true, ?string $ghostscript = 'GPL Ghostscript 10.02.1'): PdfThumbnailHealthCheckProvider
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
}
