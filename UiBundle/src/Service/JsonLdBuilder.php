<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// The pieces every bundle's structured data shares - the encoding written inside a <script>, the breadcrumb, the listing, the plain text of a rich field - kept in one place so a book, a product and a photo never publish them three slightly different ways, each entity's own graph staying in its bundle (see ShopBundle's ProductSnippetBuilder)
class JsonLdBuilder
{
    // JSON_HEX_TAG turns a "</script>" typed into any field into <, which no browser closes the tag on; JSON_INVALID_UTF8_SUBSTITUTE keeps a stray byte from turning the whole graph into false, i.e. an empty tag
    private const int FLAGS = \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE;

    // The "@id" of whoever publishes the site, built in one place so SiteBundle's publisher node and a contact block describing the same business name one entity rather than two
    public static function publisherId(string $siteUrl, bool $isPerson = false): string
    {
        return rtrim(trim($siteUrl), '/') . '/#' . ($isPerson ? 'person' : 'organization');
    }

    // A graph encoded for a <script type="application/ld+json">; empty string when there is nothing to publish
    public function encode(array $snippet): string
    {
        return [] === $snippet ? '' : (string) json_encode($snippet, self::FLAGS);
    }

    // The trail leading to a page, as the BreadcrumbList a search engine prints in place of the raw url, its levels in reading order and already resolved: only the caller can translate a name or turn a route into an address
    /** @param list<array{name: string, url: string}> $trail */
    public function breadcrumb(array $trail): array
    {
        $elements = $this->elements($trail, 0, 'item');

        // A single level is the page itself: a trail leading nowhere says nothing a url does not already say
        if (\count($elements) < 2) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    // What a listing holds, as the ItemList a search engine reads a page of cards through, each element pointing at the page where the entity's own graph lives. $offset is how many the pages before already listed, so a second page numbers its cards from where the first stopped
    /** @param list<array{name: string, url: string}> $items */
    public function itemList(array $items, int $offset = 0): array
    {
        $elements = $this->elements($items, max(0, $offset), 'url');

        if ([] === $elements) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            // What this page holds and not what the whole catalog does: a count claiming more than the elements below it is what a validator refuses
            'numberOfItems' => \count($elements),
            'itemListElement' => $elements,
        ];
    }

    // One line of plain text out of a rich field. Entities are decoded before the whitespace is collapsed, and the non-breaking space an editor leaves behind, which "\s" does not match, is collapsed with it
    public function plainText(mixed $html): string
    {
        $text = html_entity_decode(strip_tags((string) $html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
    }

    // Numbered ListItems, a level with no name or no url dropped rather than numbered: a list whose positions skip one is malformed
    private function elements(array $levels, int $position, string $urlKey): array
    {
        $elements = [];

        foreach ($levels as $level) {
            $name = trim((string) ($level['name'] ?? ''));
            $url = trim((string) ($level['url'] ?? ''));

            if ('' === $name || '' === $url) {
                continue;
            }

            $elements[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => $name,
                $urlKey => $url,
            ];
        }

        return $elements;
    }
}
