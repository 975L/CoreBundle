<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\DompdfGenerator;
use c975L\UiBundle\Service\PdfGenerator;
use c975L\UiBundle\Service\WeasyPrintGenerator;
use PHPUnit\Framework\TestCase;

// Which engine draws a site's documents, decided from that site's own configuration - what lets one codebase deployed across a fleet render as well as each server allows, with nothing to wire site by site
class PdfGeneratorTest extends TestCase
{
    // The default, and the whole reason the setting exists: the binary is asked, not the configuration
    public function testAutoTakesWeasyPrintWhereItsBinaryAnswers(): void
    {
        $this->assertSame(PdfGenerator::ENGINE_WEASYPRINT, $this->generator('auto', weasyPrintAvailable: true)->engineName());
    }

    public function testAutoFallsBackOnTheEngineTheBundleShipsWhereItDoesNot(): void
    {
        $this->assertSame(PdfGenerator::ENGINE_DOMPDF, $this->generator('auto', weasyPrintAvailable: false)->engineName());
    }

    // A site that wants every environment to draw the same document - a developer's laptop included - pins one
    public function testAPinnedEngineIsUsedWhateverTheServerCarries(): void
    {
        $this->assertSame(PdfGenerator::ENGINE_DOMPDF, $this->generator('dompdf', weasyPrintAvailable: true)->engineName());
        $this->assertSame(PdfGenerator::ENGINE_WEASYPRINT, $this->generator('weasyprint', weasyPrintAvailable: false)->engineName());
    }

    // A value nobody recognises must not leave a site with no engine at all
    public function testAnUnknownValueIsReadAsAutomatic(): void
    {
        $this->assertSame(PdfGenerator::ENGINE_WEASYPRINT, $this->generator('chromium', weasyPrintAvailable: true)->engineName());
        $this->assertSame(PdfGenerator::ENGINE_DOMPDF, $this->generator('', weasyPrintAvailable: false)->engineName());
    }

    // Asking the binary costs a process, and a document is commonly drawn twice on one request - once to be shown, once to be attached
    public function testTheBinaryIsAskedOncePerRequest(): void
    {
        $weasyPrint = $this->createMock(WeasyPrintGenerator::class);
        $weasyPrint->expects($this->once())->method('isAvailable')->willReturn(false);

        $generator = new PdfGenerator($this->createStub(DompdfGenerator::class), $weasyPrint, $this->config('auto'));

        $generator->engineName();
        $generator->engineName();
        $generator->engine();
    }

    // What every caller is handed: the picker, never one of the two engines
    public function testTheDocumentIsDrawnByWhicheverEngineWasPicked(): void
    {
        $dompdf = $this->createStub(DompdfGenerator::class);
        $dompdf->method('renderHtml')->willReturn('%PDF-dompdf');

        $generator = new PdfGenerator($dompdf, $this->weasyPrint(false), $this->config('dompdf'));

        $this->assertSame('%PDF-dompdf', $generator->renderHtml('<p>x</p>'));
    }

    private function generator(string $configured, bool $weasyPrintAvailable): PdfGenerator
    {
        return new PdfGenerator($this->createStub(DompdfGenerator::class), $this->weasyPrint($weasyPrintAvailable), $this->config($configured));
    }

    private function weasyPrint(bool $available): WeasyPrintGenerator
    {
        $weasyPrint = $this->createStub(WeasyPrintGenerator::class);
        $weasyPrint->method('isAvailable')->willReturn($available);

        return $weasyPrint;
    }

    private function config(string $engine): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($engine);

        return $configService;
    }
}
