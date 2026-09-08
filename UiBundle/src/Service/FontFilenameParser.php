<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Font;

// Guesses a Font's name/weight/style from "FamilyName-WeightStyle.ext", a best-effort saving three fields per file - several suffix segments are read, an archive naming the style apart from the weight being as common as one welding them together
class FontFilenameParser
{
    // Substring-matched against the last segment, longest keyword first so "extrabold" beats "bold"
    private const array WEIGHT_KEYWORDS = [
        'extralight' => 200,
        'ultralight' => 200,
        'semibold' => 600,
        'demibold' => 600,
        'extrabold' => 800,
        'ultrabold' => 800,
        'variable' => Font::WEIGHT_VARIABLE,
        'regular' => 400,
        'normal' => 400,
        'medium' => 500,
        'light' => 300,
        'thin' => 100,
        'black' => 900,
        'heavy' => 900,
        'bold' => 700,
    ];

    /**
     * @return array{name: string, weight: int, style: string}
     */
    public function parse(string $filename): array
    {
        $base = pathinfo($filename, PATHINFO_FILENAME);
        // Strips a variable font's axis tags (eg. "Inter-VariableFont_wght,opsz" -> "Inter-VariableFont")
        $base = preg_replace('/_[a-z]{3,4}(,[a-z]{3,4})*$/i', '', $base) ?? $base;

        $parts = preg_split('/[-_]+/', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [$base];
        $suffix = $this->parseSuffix($parts);

        // Only the segments that really were weight/style suffixes are dropped from the name - "Grand-Corps.woff2" keeps both words
        $nameParts = null === $suffix ? $parts : \array_slice($parts, 0, -$suffix['consumed']);

        return [
            'name' => $this->humanize(implode(' ', $nameParts)),
            'weight' => $suffix['weight'] ?? 400,
            'style' => $suffix['style'] ?? 'normal',
        ];
    }

    // What the filename's trailing segments stand for, and how many of them they took, or null when the last one carries neither
    // Several segments rather than the last one alone: Google names a variable italic "SourceSans3-Italic-VariableFont_wght", putting the style before the axis segment - read from the end only, it was welded into the family name and the face declared upright
    /**
     * @param list<string> $parts
     *
     * @return array{weight: int, style: string, consumed: int}|null
     */
    private function parseSuffix(array $parts): ?array
    {
        $isItalic = false;
        $weight = null;
        $consumed = 0;

        // Stops at the first segment saying neither, and never past the first one: a family needs a name left
        for ($i = \count($parts) - 1; $i >= 1; --$i) {
            $segment = strtolower($parts[$i]);
            $segmentItalic = str_contains($segment, 'italic');
            $weightSegment = str_replace('italic', '', $segment);
            $segmentWeight = '' !== $weightSegment ? $this->matchWeight($weightSegment) : null;

            if (!$segmentItalic && null === $segmentWeight) {
                break;
            }

            $isItalic = $isItalic || $segmentItalic;
            // The first weight met walking back wins, "Family-Bold-Italic" being read the same way as "Family-BoldItalic"
            $weight ??= $segmentWeight;
            ++$consumed;
        }

        if (0 === $consumed) {
            return null;
        }

        return ['weight' => $weight ?? 400, 'style' => $isItalic ? 'italic' : 'normal', 'consumed' => $consumed];
    }

    private function matchWeight(string $suffix): ?int
    {
        foreach (self::WEIGHT_KEYWORDS as $keyword => $weight) {
            if (str_contains($suffix, $keyword)) {
                return $weight;
            }
        }

        return null;
    }

    // Splits a camelCase family name (eg. "OpenSans", as bundled without spaces in most font archives) into words
    private function humanize(string $name): string
    {
        // The end of an acronym, which is where the next word starts: "IBMPlexMono" gives "IBM Plex Mono" and not "IBMPlex Mono". Guarded by a following lowercase, so "PT Sans" is not cut between its two capitals
        $name = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', ' ', $name) ?? $name;
        $name = preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', $name) ?? $name;
        // The number a family is numbered with is a word of its own: "SourceSans3" is the "Source Sans 3" of Google, and a face named "Source Sans3" answers to nothing anyone types
        $name = preg_replace('/(?<=[a-zA-Z])(?=[0-9])/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }
}
