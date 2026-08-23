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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Twig\Environment;

/**
 * The other engine, for a server that carries it: WeasyPrint draws modern CSS where DompdfGenerator draws CSS 2.1.
 *
 * Shipped with this bundle and costing nothing to anybody: it shells out to a binary, exactly as the PDF
 * thumbnails shell out to Ghostscript (see VichPdfThumbnailListener), so there is no Composer dependency to add
 * and a site without the binary simply never reaches this class (see PdfGenerator, which picks).
 *
 * Two things differ from the engine shipped as the default, and both are the caller's to know:
 *
 * - the page size is CSS here and an argument there, so the size asked for is written into the document as an
 *   "@page" rule rather than handed to the engine;
 * - remote fetching cannot be switched off on this command line, where Dompdf refuses it outright. A print
 *   template is this ecosystem's own and names no remote url, but a site turning this engine on is trusting the
 *   markup it prints - which is the reason the default stays the one that can say no.
 */
class WeasyPrintGenerator implements PdfGeneratorInterface
{
    // Enough for a long document on a loaded server, and short enough that a hung binary is not a request held open forever
    private const int TIMEOUT = 60;

    // What the command is called when a site says nothing: the name alone, found on the PATH
    private const string DEFAULT_BINARY = 'weasyprint';

    public function __construct(
        private readonly Environment $twig,
        private readonly ConfigServiceInterface $configService,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    // A setting and not a container parameter: on a managed host the command commonly sits in a virtual environment of its own, and where it sits is something an admin knows and a deployment does not
    private function binary(): string
    {
        $binary = trim((string) $this->configService->get('ui-pdf-weasyprint-path'));

        return '' === $binary ? self::DEFAULT_BINARY : $binary;
    }

    public function render(string $template, array $context = [], array $options = []): string
    {
        return $this->renderHtml($this->twig->render($template, $context), $options);
    }

    public function renderHtml(string $html, array $options = []): string
    {
        $basePath = $options['basePath'] ?? $this->projectDir . '/public/';

        // "-" twice: the markup goes in on the standard input and the file comes back on the standard output, so nothing is written to disk for a document that is served straight back
        $process = new Process([$this->binary(), '-', '-', '--encoding', 'utf-8', '--base-url', $basePath]);
        $process->setInput($this->withPageRule($html, $options));
        $process->setTimeout(self::TIMEOUT);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    // Whether the binary answers at all, which is what decides between the two engines (see PdfGenerator)
    public function isAvailable(): bool
    {
        $process = new Process([$this->binary(), '--version']);
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * Writes the page size asked for into the document, this engine reading it from CSS where the other takes it as an argument.
     *
     * Appended to the head rather than replacing what the template declares: a print template states its own
     * margins, and only the size is being settled here.
     *
     * @param array<string, mixed> $options
     */
    private function withPageRule(string $html, array $options): string
    {
        $paper = $options['paper'] ?? null;

        if (null === $paper) {
            return $html;
        }

        $size = \is_array($paper)
            ? sprintf('%smm %smm', $paper[0], $paper[1])
            : $paper . ('landscape' === ($options['orientation'] ?? 'portrait') ? ' landscape' : '');

        $rule = sprintf('<style>@page { size: %s; }</style>', $size);

        return str_contains($html, '</head>')
            ? str_replace('</head>', $rule . '</head>', $html)
            : $rule . $html;
    }
}
