<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Reads a model's answer to numbered excerpts, shaped {"answer": "...", "sources": [numbers]}: the links come from the excerpts alone, a number naming none dropped, so no url the model wrote ever reaches the page
final class AiExcerptReply
{
    // The answer and the excerpts it names; a reply that isn't the JSON asked for is kept as the answer, its "[2]" marks and its closing "Sources: [1, 2]" line read as the excerpts it names, every excerpt standing for them when it names none
    /**
     * @param list<array{url: string, title: string, content: string}> $excerpts
     *
     * @return array{0: string, 1: list<array{url: string, title: string}>}
     */
    public static function parse(string $reply, array $excerpts): array
    {
        $data = preg_match('/\{.*\}/s', $reply, $matches) ? json_decode($matches[0], true) : null;
        if (\is_array($data) && \is_string($data['answer'] ?? null)) {
            return [self::clean($data['answer']), self::sources(self::named((array) ($data['sources'] ?? []), $excerpts))];
        }

        preg_match_all('/\[(\d+(?:\s*,\s*\d+)*)\]/', $reply, $marks);
        $named = self::named(preg_split('/\s*,\s*/', implode(',', $marks[1])) ?: [], $excerpts);

        return [self::clean($reply), self::sources([] === $named ? $excerpts : $named)];
    }

    // The excerpts the numbers name, a number naming none dropped
    /**
     * @param array<mixed>                                             $numbers
     * @param list<array{url: string, title: string, content: string}> $excerpts
     *
     * @return list<array{url: string, title: string, content: string}>
     */
    private static function named(array $numbers, array $excerpts): array
    {
        $named = [];
        foreach ($numbers as $number) {
            if (is_numeric($number) && isset($excerpts[(int) $number - 1])) {
                $named[] = $excerpts[(int) $number - 1];
            }
        }

        return $named;
    }

    // The answer as plain text, without its "Sources: [1]" line nor its "[1]" marks, which the links shown stand for
    private static function clean(string $answer): string
    {
        $answer = (string) preg_replace('/\s*\bSources?\s*:?\s*\[[\d,\s]*\]\.?\s*$/iu', '', strip_tags($answer));

        return trim((string) preg_replace('/\s*\[\d+(?:\s*,\s*\d+)*\]/', '', $answer));
    }

    // One link per url, in the order the excerpts came
    /**
     * @param list<array{url: string, title: string, content: string}> $excerpts
     *
     * @return list<array{url: string, title: string}>
     */
    private static function sources(array $excerpts): array
    {
        $sources = [];
        foreach ($excerpts as $excerpt) {
            $sources[$excerpt['url']] ??= ['url' => $excerpt['url'], 'title' => $excerpt['title']];
        }

        return array_values($sources);
    }
}
