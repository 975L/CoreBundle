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
    // The three bands the "pick a group" screen is read in, in the order it draws them: what the site says about itself, what its bundles brought, and what is only ever touched once
    // A drawer named by a bundle and listed in neither falls in the middle one, which is what a satellite's drawer is (see ECOSYSTEM.md §15) - so a bundle installed tomorrow lands where it belongs without naming itself here
    public const string BAND_SITE = 'site';

    public const string BAND_FEATURES = 'features';

    public const string BAND_TECHNICAL = 'technical';

    private const array BANDS = [
        self::BAND_SITE => ['general', 'legal', 'credits', 'email', 'theme', 'seo', 'site'],
        self::BAND_TECHNICAL => ['system', 'security', 'backup', 'messenger', 'health_check'],
    ];

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

    // The same rows, cut into the three bands the screen reads in - a hundred and sixty drawers laid flat drown the five an editor opens on the day the site goes live
    // A band holding nothing is left out rather than drawn empty: a site without a single satellite has no features to show
    /**
     * @param array<string, int> $counts count per group, keyed by slug
     *
     * @return array<string, array<string, int>> band => group => count
     */
    public function bands(array $counts): array
    {
        $bands = [];
        foreach ([self::BAND_SITE, self::BAND_FEATURES, self::BAND_TECHNICAL] as $band) {
            $rows = array_filter($counts, fn (string $group): bool => $band === $this->band($group), \ARRAY_FILTER_USE_KEY);
            if ([] !== $rows) {
                $bands[$band] = $this->sortByLabel($rows);
            }
        }

        return $bands;
    }

    private function band(string $group): string
    {
        foreach (self::BANDS as $band => $groups) {
            if (\in_array($group, $groups, true)) {
                return $band;
            }
        }

        return self::BAND_FEATURES;
    }
}
