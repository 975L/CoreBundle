<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use Symfony\Contracts\Translation\TranslatorInterface;

// What a config group is called on screen, and the order those names come in - the group itself being stored as an English slug, which is neither what the admin reads nor what they look the row up by
class ConfigGroupLabelResolver
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function label(?string $group): string
    {
        return $group ? $this->translator->trans('label.group_' . $group, [], 'config') : '';
    }

    /**
     * Orders the rows of the "pick a group" screen on the label each one shows.
     *
     * Sorted on the slug, "IA", "Sauvegarde" and "Crédits" came out in the order "ai", "backup", "credits" -
     * alphabetical to nobody reading the screen. Compared through Collator when the intl extension is there, so an
     * accented label sorts where a reader looks for it; through a plain comparison otherwise, this bundle not
     * requiring that extension.
     *
     * @param array<string, int> $counts count per group, keyed by slug
     *
     * @return array<string, int>
     */
    public function sortByLabel(array $counts): array
    {
        $labels = [];
        foreach (array_keys($counts) as $group) {
            $labels[$group] = $this->label($group);
        }

        $collator = class_exists(\Collator::class) ? new \Collator(\Locale::getDefault()) : null;
        uksort($counts, fn (string $a, string $b): int => null !== $collator
            ? (int) $collator->compare($labels[$a], $labels[$b])
            : strnatcasecmp($labels[$a], $labels[$b]));

        return $counts;
    }
}
