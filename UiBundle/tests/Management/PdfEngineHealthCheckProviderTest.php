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
use c975L\UiBundle\Management\PdfEngineHealthCheckProvider;
use c975L\UiBundle\Service\PdfGenerator;
use c975L\UiBundle\Service\WeasyPrintGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

// Which engine actually draws this site's PDFs - the whole point of leaving the setting on "auto" being that nothing on the site says which one it landed on until this row does
class PdfEngineHealthCheckProviderTest extends TestCase
{
    public function testTheKindIsTheOneTheDashboardGroupsOn(): void
    {
        $this->assertSame('pdf-engine', $this->provider('auto', true, 'weasyprint')->getKind());
        $this->assertSame(PdfEngineHealthCheckProvider::KIND, $this->provider('auto', true, 'weasyprint')->getKind());
    }

    public function testTheCheckPointsAtTheSettingItIsAbout(): void
    {
        $check = $this->provider('auto', true, 'weasyprint')->runChecks()[0];

        $this->assertSame('ui-pdf-engine', $check['url']);
    }

    // Nothing is wrong with a site drawing its documents, whichever engine it landed on
    public function testAnEngineThatAnswersIsReportedWithoutAlarm(): void
    {
        foreach ([['auto', true, 'weasyprint'], ['auto', false, 'dompdf'], ['dompdf', false, 'dompdf'], ['weasyprint', true, 'weasyprint']] as [$configured, $available, $inUse]) {
            $check = $this->provider($configured, $available, $inUse)->runChecks()[0];

            $this->assertSame(HealthCheckResult::STATUS_OK, $check['status']);
        }
    }

    // The one case worth a warning: a site that pinned WeasyPrint on a server where the binary does not answer gets no PDF at all
    public function testPinningTheEngineAServerDoesNotCarryIsAnError(): void
    {
        $check = $this->provider('weasyprint', false, 'weasyprint')->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $check['status']);
    }

    // Read off the configuration as typed: a value with a stray space or a capital must not read as a different engine
    public function testTheSettingIsReadWhateverItsCaseAndSpacing(): void
    {
        $check = $this->provider('  WeasyPrint  ', false, 'weasyprint')->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $check['status']);
        $this->assertSame('weasyprint', $check['details']['configured']);
    }

    // Read per run and never stored: a server that gains or loses the binary between two runs changes nothing in the database
    public function testTheDetailsSayWhatWasAskedForAndWhatWasLandedOn(): void
    {
        $check = $this->provider('auto', false, 'dompdf')->runChecks()[0];

        $this->assertSame(['configured' => 'auto', 'inUse' => 'dompdf', 'weasyprintAvailable' => false], $check['details']);
    }

    public function testTheSummaryNamesTheEngineInUse(): void
    {
        $check = $this->provider('auto', true, 'weasyprint')->runChecks()[0];

        $this->assertSame('label.pdf_engine_summary_available[%engine%=weasyprint]', $check['summary']);
    }

    public function testTheSummarySaysSoWhereTheOtherBinaryIsMissing(): void
    {
        $check = $this->provider('auto', false, 'dompdf')->runChecks()[0];

        $this->assertSame('label.pdf_engine_summary_missing[%engine%=dompdf]', $check['summary']);
    }

    private function provider(string $configured, bool $weasyPrintAvailable, string $inUse): PdfEngineHealthCheckProvider
    {
        $pdfGenerator = $this->createStub(PdfGenerator::class);
        $pdfGenerator->method('engineName')->willReturn($inUse);

        $weasyPrint = $this->createStub(WeasyPrintGenerator::class);
        $weasyPrint->method('isAvailable')->willReturn($weasyPrintAvailable);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($configured);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []): string => [] === $parameters
                ? $id
                : $id . '[' . implode(',', array_map(fn ($k, $v): string => $k . '=' . $v, array_keys($parameters), $parameters)) . ']'
        );

        return new PdfEngineHealthCheckProvider($pdfGenerator, $weasyPrint, $configService, $translator);
    }
}
