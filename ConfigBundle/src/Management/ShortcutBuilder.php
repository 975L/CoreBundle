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

// Merges the dashboard shortcuts contributed by every ShortcutProvider (bundles depending on ConfigBundle) into categories (see ShortcutProviderInterface), each rendered as a titled row of tiles: an admin looking for what a tile does reads one heading rather than scanning a single grid where an export sat next to a toggle
class ShortcutBuilder
{
    // Fallback category for a shortcut not opting into one (see ShortcutProviderInterface::getShortcuts())
    private const string OTHER_CATEGORY_LABEL = 'label.shortcuts_category_other';
    private const string OTHER_CATEGORY_TRANSLATION_DOMAIN = 'config';

    public function __construct(
        private readonly iterable $shortcutProviders,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Returns the categories, ordered by their translated label, each holding its shortcuts ordered by their own (already translated) label - the template renders one row per category, the tiles of a same-themed group coming from any number of bundles
    /** @return list<array{label: string, shortcuts: list<array<string, mixed>>}> */
    public function getCategories(): array
    {
        $shortcuts = ProviderMerger::merge($this->shortcutProviders, fn (ShortcutProviderInterface $provider) => $provider->getShortcuts());

        $categories = [];
        foreach ($shortcuts as $shortcut) {
            $category = $shortcut['category'] ?? ['label' => self::OTHER_CATEGORY_LABEL, 'translation_domain' => self::OTHER_CATEGORY_TRANSLATION_DOMAIN];
            $key = $category['translation_domain'] . '.' . $category['label'];
            $categories[$key] ??= ['label' => $this->translator->trans($category['label'], [], $category['translation_domain']), 'shortcuts' => []];
            $categories[$key]['shortcuts'][] = $shortcut;
        }

        uasort($categories, fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));

        return array_values(array_map(static function (array $category): array {
            uasort($category['shortcuts'], fn (array $a, array $b) => strcasecmp($a['label'], $b['label']));
            $category['shortcuts'] = array_values($category['shortcuts']);

            return $category;
        }, $categories));
    }
}
