<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Renders a PDF out of HTML, so a page a site already publishes can also be handed over as a file.
 *
 * An interface and not a class on purpose: which engine draws the pages is a question about the *server*, not
 * about this bundle. The one shipped here is pure PHP and runs wherever Composer does (see DompdfGenerator);
 * a site whose host carries the native libraries a browser engine needs aliases this contract onto its own
 * implementation, and not one caller changes. That is what lets a bundle published on Packagist offer PDFs at
 * all: it cannot require Python, a headless Chromium or a Docker daemon of whoever installs it.
 *
 * A PDF is answered as a string of bytes rather than written to a file: most of them are served straight back
 * to a browser or attached to an email, and the two callers that do want a file own where it goes.
 */
interface PdfGeneratorInterface
{
    /**
     * The PDF a Twig template draws, as raw bytes.
     *
     * The template is a print template, not the page's own: whatever engine is behind this reads far less CSS
     * than a browser, and a document meant to be printed is not laid out like a document meant to be scrolled.
     *
     * @param array<string, mixed> $context the template's own variables
     * @param array<string, mixed> $options 'paper' (a named size or [width, height] in millimetres),
     *                                      'orientation' ('portrait'|'landscape'), 'basePath' (the directory
     *                                      a relative image path is resolved against, defaulting to the
     *                                      site's public directory)
     */
    public function render(string $template, array $context = [], array $options = []): string;

    /**
     * The same, from markup already rendered - what a caller holding HTML rather than a template needs.
     *
     * @param array<string, mixed> $options as above
     */
    public function renderHtml(string $html, array $options = []): string;
}
