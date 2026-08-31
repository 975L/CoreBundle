<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

// Turns a page's rendered HTML into a queryable document, the same way for every checker reading markup off a live site (ContentQualityClient, AccessibilityClient). Native DOMDocument/DOMXPath, no dependency - and the libxml precautions below are the whole reason this is shared rather than written twice: both are easy to get subtly wrong, and a checker getting them wrong reports a site's accented titles as mangled or silences another part of the process
class HtmlDocument
{
    // Builds the XPath reader for a page's markup. Static, this holding no state and needing no collaborator - a checker calls it where it would otherwise have opened a DOMDocument itself
    public static function xpath(string $html): \DOMXPath
    {
        // Restored right after: the setting is process-wide, and left on it silences the parse errors every other libxml reader of the process relies on - ImageMagick's own SVG parser included, which then renders a malformed SVG instead of refusing it
        $useInternalErrors = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // Forces UTF-8 interpretation regardless of the page's own <meta charset> (or lack thereof) - DOMDocument defaults to ISO-8859-1 otherwise, mangling accented characters
        $dom->loadHTML('<?xml encoding="utf-8">' . $html, \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($useInternalErrors);

        return new \DOMXPath($dom);
    }

    // DOMXPath::query answers a list of plain nodes, while every expression the checkers read selects elements - and only an element carries the attributes they read off it
    public static function elements(\DOMXPath $xpath, string $expression): \Generator
    {
        foreach ($xpath->query($expression) as $node) {
            if ($node instanceof \DOMElement) {
                yield $node;
            }
        }
    }
}
