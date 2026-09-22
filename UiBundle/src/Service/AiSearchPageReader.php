<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\HtmlDocument;

// Reads what a page says out of its rendered html: the text of its <main> (the <body> when it has none), cut into passages of about CHUNK_LENGTH characters on paragraph boundaries. The site's chrome - menus, header, footer, forms, scripts - says the same on every page and would answer every question, so it is left out, as is anything marked data-ai-search-ignore
class AiSearchPageReader
{
    // Long enough to carry a whole answer, short enough that the six sent with a question stay a small prompt
    public const int CHUNK_LENGTH = 1200;

    private const string SKIPPED = 'self::script or self::style or self::noscript or self::template or self::svg or self::nav or self::header or self::footer or self::aside or self::form or @hidden or @aria-hidden="true" or @data-ai-search-ignore';

    private const string BLOCKS = 'self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6 or self::p or self::li or self::dt or self::dd or self::th or self::td or self::blockquote or self::figcaption or self::summary';

    // Null for a page asking not to be indexed, or saying nothing
    /** @return array{title: string, locale: string, chunks: list<string>}|null */
    public function read(string $html, string $defaultLocale): ?array
    {
        $xpath = HtmlDocument::xpath($html);

        $robots = strtolower((string) $xpath->evaluate('string(//meta[@name="robots"]/@content)'));
        if (str_contains($robots, 'noindex')) {
            return null;
        }

        $root = $xpath->query('//main')->item(0) ?? $xpath->query('//body')->item(0);
        if (null === $root) {
            return null;
        }

        // Every text block not sitting inside something skipped, and not holding another block itself - a <li> wrapping a <p> is read once, through the <p>
        $paragraphs = [];
        $query = './/*[(' . self::BLOCKS . ') and not(ancestor-or-self::*[' . self::SKIPPED . ']) and not(.//*[' . self::BLOCKS . '])]';
        foreach ($xpath->query($query, $root) as $node) {
            $text = $this->clean($node->textContent);
            if ('' !== $text && end($paragraphs) !== $text) {
                $paragraphs[] = $text;
            }
        }

        if ([] === $paragraphs) {
            return null;
        }

        $title = $this->clean((string) $xpath->evaluate('string(//title)'));
        $locale = substr((string) $xpath->evaluate('string(/html/@lang)'), 0, 10);

        return [
            'title' => mb_substr('' !== $title ? $title : $paragraphs[0], 0, 255),
            'locale' => '' !== $locale ? str_replace('_', '-', $locale) : $defaultLocale,
            'chunks' => $this->chunk($paragraphs),
        ];
    }

    private function clean(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    // Paragraphs gathered until the next one would overflow CHUNK_LENGTH; a single paragraph longer than that is cut on its own
    // @param list<string> $paragraphs
    // @return list<string>
    private function chunk(array $paragraphs): array
    {
        $chunks = [];
        $current = '';
        foreach ($paragraphs as $paragraph) {
            foreach (mb_str_split($paragraph, self::CHUNK_LENGTH) as $piece) {
                if ('' !== $current && mb_strlen($current) + mb_strlen($piece) + 1 > self::CHUNK_LENGTH) {
                    $chunks[] = $current;
                    $current = '';
                }
                $current = '' === $current ? $piece : $current . "\n" . $piece;
            }
        }
        if ('' !== $current) {
            $chunks[] = $current;
        }

        return $chunks;
    }
}
