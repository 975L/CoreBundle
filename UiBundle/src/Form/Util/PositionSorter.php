<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Util;

// Orders the rows of a sortable collection stored as a plain array (a Block's JSON "data"), where the array order is what gets rendered: ea-sortable.js writes each row's "position", this reads it back on submit and drops it
final class PositionSorter
{
    // A single row without a position (never dragged, nothing renumbered yet) keeps the submitted order for all of them, two rows on the same position keep the order they were submitted in (usort is stable), and a non-array entry (a forged POST) is left for the form's validation
    public static function sort(array $rows): array
    {
        $ranked = [];
        $positioned = true;
        foreach (array_values($rows) as $index => $row) {
            $position = \is_array($row) ? ($row['position'] ?? null) : null;
            $positioned = $positioned && (!\is_array($row) || is_numeric($position));
            $ranked[] = [is_numeric($position) ? (int) $position : $index, $row];
        }

        if ($positioned) {
            usort($ranked, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        }

        return array_map(
            static fn (array $entry): mixed => \is_array($entry[1]) ? array_diff_key($entry[1], ['position' => true]) : $entry[1],
            $ranked,
        );
    }
}
