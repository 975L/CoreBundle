<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Finds the <text> an SVG still draws with a font instead of with paths - the one defect an author cannot see, their own machine having the font installed. Served through an <img>, an SVG is rendered as an isolated document: it reaches neither the page's @font-face rules nor its stylesheet, so the font has to be on the visitor's own machine or the glyphs fall back to whatever the renderer picks. Same story server-side, where SvgRasterizer flattens icon roles with the fonts the server happens to carry. Converting the text to paths is the only fix, and it is what this warns about (see SvgTextWarningListener and SiteBundle's SvgFontsHealthCheckProvider)
class SvgTextDetector
{
    // Elements that paint glyphs, whatever namespace prefix the document gave them
    private const TEXT_ELEMENTS = ['text', 'tspan', 'textPath'];

    // Read before the file is parsed at all, so handing this a 100M video costs one fread and not an XML parse
    private const SNIFF_BYTES = 1024;

    // Past this, a file claiming to be an SVG is not one anyone is going to fix by vectorizing its text
    private const MAX_BYTES = 8 * 1024 * 1024;

    // Whether the file holds SVG markup at all, decided on content and never on the name: an icon role's stored file already carries the role's own .ico/.png extension while still holding the SVG that was uploaded (see UiMediaNamer). Asked apart from drawsText() so a caller can tell "not an SVG" from "an SVG with nothing to fix"
    public function isSvg(string $absolutePath): bool
    {
        return null !== $this->load($absolutePath);
    }

    // True when the file is an SVG still drawing at least one run of text
    public function drawsText(string $absolutePath): bool
    {
        return [] !== $this->textNodes($absolutePath);
    }

    /**
     * Font families that text depends on, deduplicated and in document order - for a message naming what
     * has to be installed, or vectorized away. Empty for a file drawing no text at all, and empty too for
     * text carrying no family of its own, which falls back to the renderer's default.
     *
     * @return list<string>
     */
    public function fontFamilies(string $absolutePath): array
    {
        $families = [];

        foreach ($this->textNodes($absolutePath) as $node) {
            foreach ($this->familiesOf($node) as $family) {
                $families[$family] = true;
            }
        }

        return array_keys($families);
    }

    /**
     * @return list<\SimpleXMLElement>
     */
    private function textNodes(string $absolutePath): array
    {
        $svg = $this->load($absolutePath);
        if (null === $svg) {
            return [];
        }

        // local-name() rather than a registered namespace: an SVG in the wild may declare the namespace, prefix it, or omit it entirely, and all three still render
        $predicate = implode(' or ', array_map(static fn (string $name): string => 'local-name()="' . $name . '"', self::TEXT_ELEMENTS));
        $nodes = $svg->xpath('//*[' . $predicate . ']');

        return null === $nodes ? [] : array_values($nodes);
    }

    // Null for anything that is not an SVG document. Re-read on each call rather than memoized: a cache would put state into a service the upload listener and the health check both hold, to save one parse of a file measured in kilobytes
    private function load(string $absolutePath): ?\SimpleXMLElement
    {
        if (!is_file($absolutePath) || filesize($absolutePath) > self::MAX_BYTES) {
            return null;
        }

        // Cheap gate first, so handing this a 100M video costs one fread instead of an XML parse
        $head = (string) file_get_contents($absolutePath, false, null, 0, self::SNIFF_BYTES);
        if (false === stripos($head, '<svg')) {
            return null;
        }

        $useInternalErrors = libxml_use_internal_errors(true);
        $svg = simplexml_load_string((string) file_get_contents($absolutePath), 'SimpleXMLElement', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        // The sniff only says "<svg" appears near the top; the root element is what settles it
        return false !== $svg && 'svg' === $svg->getName() ? $svg : null;
    }

    /**
     * A family lives either in the "font-family" attribute or inside "style", and the two can disagree, so
     * both are read. Only the head of each list is kept: "'Riffic Free', sans-serif" names one font to go
     * and find, the rest of the list being what the renderer falls back to precisely because it failed.
     *
     * @return list<string>
     */
    private function familiesOf(\SimpleXMLElement $node): array
    {
        $declarations = [(string) ($node['font-family'] ?? '')];

        if (preg_match('/(?:^|;)\s*font-family\s*:\s*([^;]+)/i', (string) ($node['style'] ?? ''), $matches)) {
            $declarations[] = $matches[1];
        }

        $families = [];
        foreach ($declarations as $declaration) {
            $family = trim(trim(explode(',', $declaration)[0]), "'\"");
            if ('' !== $family) {
                $families[] = $family;
            }
        }

        return $families;
    }
}
