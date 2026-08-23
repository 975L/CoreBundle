<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\UiBundle\Contract\PdfGeneratorInterface;

/**
 * Which engine actually draws the pages, decided per site from its own configuration rather than from its code.
 *
 * This is the service every caller gets when it asks for PdfGeneratorInterface (see config/services.yaml, which
 * aliases the contract here): the two engines behind it are an implementation detail nothing else has to know.
 *
 * "ui-pdf-engine" takes three values:
 *
 * - "auto" (the default): WeasyPrint where its binary answers, Dompdf everywhere else. One codebase deployed
 *   across a fleet then renders as well as each server allows, with nothing to wire site by site;
 * - "dompdf": the engine this bundle ships, whatever the server carries - what a site pins when it wants every
 *   environment to draw the same document, its developer's laptop included;
 * - "weasyprint": that engine and no fallback, so a server that loses the binary says so loudly rather than
 *   quietly going back to drawing a document that has been proofread against the other.
 *
 * The answer is held for the request and no longer: asking the binary costs a process, and a document is commonly
 * drawn twice on one request - once to be shown, once to be attached.
 */
class PdfGenerator implements PdfGeneratorInterface
{
    public const string ENGINE_AUTO = 'auto';
    public const string ENGINE_DOMPDF = 'dompdf';
    public const string ENGINE_WEASYPRINT = 'weasyprint';

    private ?PdfGeneratorInterface $engine = null;

    public function __construct(
        private readonly DompdfGenerator $dompdfGenerator,
        private readonly WeasyPrintGenerator $weasyPrintGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function render(string $template, array $context = [], array $options = []): string
    {
        return $this->engine()->render($template, $context, $options);
    }

    public function renderHtml(string $html, array $options = []): string
    {
        return $this->engine()->renderHtml($html, $options);
    }

    // What a health check reports, and what the two callers above draw with
    public function engine(): PdfGeneratorInterface
    {
        return $this->engine ??= match ($this->configured()) {
            self::ENGINE_WEASYPRINT => $this->weasyPrintGenerator,
            self::ENGINE_DOMPDF => $this->dompdfGenerator,
            // The binary is asked, not the configuration: a fleet sharing one codebase has servers that carry it and servers that do not
            default => $this->weasyPrintGenerator->isAvailable() ? $this->weasyPrintGenerator : $this->dompdfGenerator,
        };
    }

    // The name of the engine in use, which is what the dashboard prints
    public function engineName(): string
    {
        return $this->engine() instanceof WeasyPrintGenerator ? self::ENGINE_WEASYPRINT : self::ENGINE_DOMPDF;
    }

    // Anything a site typed that is not one of the three is read as "auto": a value nobody recognises must not leave a site with no engine at all
    private function configured(): string
    {
        $configured = strtolower(trim((string) $this->configService->get('ui-pdf-engine')));

        return \in_array($configured, [self::ENGINE_DOMPDF, self::ENGINE_WEASYPRINT], true) ? $configured : self::ENGINE_AUTO;
    }
}
