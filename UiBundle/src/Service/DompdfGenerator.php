<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\PdfGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

/**
 * The PDF engine this bundle ships: pure PHP, so it runs wherever Composer does.
 *
 * Chosen over the engines that draw better - a headless browser, or one of the Python renderers - for one reason:
 * this bundle is installed by whoever runs `composer require`, and it cannot impose a Python runtime, a Chromium
 * binary or a Docker daemon on them. A site whose own server carries what a better engine needs aliases
 * PdfGeneratorInterface onto its own class and nothing else moves.
 *
 * What that costs is CSS: no flexbox, no grid, no custom properties. Print templates are written against that
 * - absolute positioning, stated lengths, millimetres - and never share a stylesheet with the screen.
 */
class DompdfGenerator implements PdfGeneratorInterface
{
    // Millimetres to the points a PDF is measured in, which is what a paper size stated as [width, height] is converted with
    private const float MM_TO_POINTS = 72 / 25.4;

    public function __construct(
        private readonly Environment $twig,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function render(string $template, array $context = [], array $options = []): string
    {
        return $this->renderHtml($this->twig->render($template, $context), $options);
    }

    public function renderHtml(string $html, array $options = []): string
    {
        $dompdf = new Dompdf($this->options($options));

        // Where a relative image path is resolved from, which is the one thing a print template needs of the server: the pictures it draws are the site's own files, named as the media library stores them
        $dompdf->setBasePath($options['basePath'] ?? $this->projectDir . '/public/');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($this->paper($options['paper'] ?? 'A4'), $options['orientation'] ?? 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function options(array $options): Options
    {
        $engine = new Options();

        // Never: a print template is rendered server-side, so an "src" pointing anywhere would let whatever wrote that markup make this server fetch a url of its choosing - the shape of every SSRF there is. Every picture a document draws is a local file under the base path above
        $engine->setIsRemoteEnabled(false);

        // The document root a local path may not climb out of, for the same reason: a template resolving "../../.env" would otherwise read it into the file
        $engine->setChroot([$options['basePath'] ?? $this->projectDir . '/public/']);

        // Off, and not because of what a template of ours would do: this bundle draws documents out of content an admin typed, and an engine executing PHP inside markup is a remote-code hole with an editor at one end
        $engine->setIsPhpEnabled(false);

        $engine->setIsHtml5ParserEnabled(true);
        $engine->setDefaultFont('DejaVu Sans');

        return $engine;
    }

    /**
     * A named size as the engine knows it, or a pair of millimetres for a document that is an object rather than a page - a card, a ticket, a label.
     *
     * @return string|array{0: float, 1: float, 2: float, 3: float}
     */
    private function paper(string | array $paper): string | array
    {
        if (\is_string($paper)) {
            return $paper;
        }

        return [0.0, 0.0, round((float) $paper[0] * self::MM_TO_POINTS, 2), round((float) $paper[1] * self::MM_TO_POINTS, 2)];
    }
}
