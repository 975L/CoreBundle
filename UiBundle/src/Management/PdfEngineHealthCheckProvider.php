<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Service\PdfGenerator;
use c975L\UiBundle\Service\WeasyPrintGenerator;
use Symfony\Contracts\Translation\TranslatorInterface;

// Says which engine actually draws this site's PDFs, which is the whole point of leaving the setting on "auto": one codebase deployed across a fleet renders as well as each server allows, and nothing on the site says which one it landed on until this row does.
//
// The same reasoning as PdfThumbnailHealthCheckProvider, and for the same kind of thing: an optional binary the site shells out to. A server that gains or loses it between two runs changes nothing in the database, so the answer is read per run rather than stored.
class PdfEngineHealthCheckProvider implements HealthCheckProviderInterface
{
    public const string KIND = 'pdf-engine';

    public function __construct(
        private readonly PdfGenerator $pdfGenerator,
        private readonly WeasyPrintGenerator $weasyPrintGenerator,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return self::KIND;
    }

    public function runChecks(): array
    {
        $configured = strtolower(trim((string) $this->configService->get('ui-pdf-engine')));
        $available = $this->weasyPrintGenerator->isAvailable();
        $inUse = $this->pdfGenerator->engineName();

        return [[
            'url' => 'ui-pdf-engine',
            'label' => $this->translator->trans('label.ui_pdf_engine', [], 'site_config'),
            'status' => $this->status($configured, $available),
            'summary' => $this->summary($inUse, $available),
            'details' => ['configured' => $configured, 'inUse' => $inUse, 'weasyprintAvailable' => $available],
        ]];
    }

    // The one case worth a warning: a site that pinned WeasyPrint on a server where the binary does not answer gets no PDF at all, where "auto" quietly draws them with the other engine
    private function status(string $configured, bool $available): string
    {
        return PdfGenerator::ENGINE_WEASYPRINT === $configured && !$available
            ? HealthCheckResult::STATUS_ERROR
            : HealthCheckResult::STATUS_OK;
    }

    private function summary(string $inUse, bool $available): string
    {
        return $this->translator->trans(
            $available ? 'label.pdf_engine_summary_available' : 'label.pdf_engine_summary_missing',
            ['%engine%' => $inUse],
            'ui'
        );
    }
}
