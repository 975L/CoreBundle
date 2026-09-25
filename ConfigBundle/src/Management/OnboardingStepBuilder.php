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

// Builds the guided-tour steps from MenuBuilder's already-aggregated menus/links, covering every entry so the tour reflects the whole sidebar, then the dashboard header's ecosystem links (see getHeaderSteps()) - a menu or a link needing a role the current user lacks (see MenuProviderInterface) is skipped though, since its sidebar target isn't even rendered for them. 'description' (see MenuProviderInterface) stays optional - a step for an item without one just shows its label, no explanatory text. Each step carries the item's own resolved URL rather than an invented id/slug: assets/js/onboarding-tour.js matches it against the sidebar's own `a[href]` (no EasyAdmin template override needed, see Sidebar/Item.html.twig)
class OnboardingStepBuilder
{
    public function __construct(
        private readonly MenuBuilder $menuBuilder,
        private readonly MenuEntryResolver $menuEntryResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // [{url, label, description}], one per menu/link (both kinds needing a role the user lacks excluded) - every entry the sidebar draws in a section, menu or internal link alike, follows MenuBuilder::getOrderedMenus(), the same essential-then-advanced-across-sections order the sidebar itself renders, with the links leaving the admin (already alphabetical/pinned-last, matching their own "Liens" section) appended after, that section being drawn last
    public function getSteps(): array
    {
        $steps = [];

        foreach ($this->menuBuilder->getOrderedMenus() as $entry) {
            if (!$this->menuEntryResolver->isGranted($entry)) {
                continue;
            }

            // A step is highlighted by matching its url against the sidebar's own href, so both kinds are spelled the way the sidebar spells them
            $steps[] = $this->buildStep($this->menuEntryResolver->url($entry), $entry);
        }

        // What is left is every link leaving the admin, the ones getOrderedMenus() skips because the sidebar gathers them in its own section, below every menu
        foreach ($this->menuBuilder->getLinks() as $link) {
            if (!MenuBuilder::leavesTheAdmin($link) || !$this->menuEntryResolver->isGranted($link)) {
                continue;
            }

            $steps[] = $this->buildStep($this->menuEntryResolver->url($link), $link);
        }

        return $steps;
    }

    // [{url, label, description, narration}], one per link of the dashboard's header leaving for the ecosystem - the tour highlights any a[href] of the page, not only the sidebar's, so they are walked the same way (see management/index.html.twig)
    public function getHeaderSteps(): array
    {
        return [
            $this->buildStep(EcosystemUrls::TUTORIALS, ['label' => 'label.tutorials', 'description' => 'label.tutorials_help', 'narration' => 'narration.tutorials', 'translation_domain' => 'config']),
            $this->buildStep(EcosystemUrls::BLOCK_SHOWCASE, ['label' => 'label.block_showcase', 'description' => 'label.block_showcase_help', 'narration' => 'narration.block_showcase', 'translation_domain' => 'config']),
        ];
    }

    // [{url, label, description, narration, highlight}], the dashboard's unused features panel when it shows anything - pointed at by its own selector, its links being the sidebar's own hrefs a url match would find there first (see _unused_features.html.twig)
    public function getUnusedFeaturesSteps(array $features): array
    {
        if ([] === $features) {
            return [];
        }

        return [[
            ...$this->buildStep('', ['label' => 'label.unused_features', 'description' => 'label.unused_features_intro', 'narration' => 'narration.unused_features', 'translation_domain' => 'config']),
            'highlight' => '[data-unused-features]',
        ]];
    }

    // Same label/description resolution as MenuBuilder::getMenuItems() for a link (see its 'label_parameters' handling) - kept in sync by hand since both operate on the same MenuProviderInterface item shape
    private function buildStep(string $url, array $item): array
    {
        $label = $this->translator->trans($item['label'], $item['label_parameters'] ?? [], $item['translation_domain']);
        $description = empty($item['description']) ? '' : $this->translator->trans($item['description'], [], $item['translation_domain']);

        return [
            'url' => $url,
            'label' => $label,
            'description' => $description,
            // What the step sounds like spoken, read by the films of the back office and drawn nowhere. An entry nobody has written a sentence for falls back to its caption, better said badly than not said at all
            'narration' => empty($item['narration'])
                ? trim(rtrim($label, " \t.") . '. ' . $description)
                : $this->translator->trans($item['narration'], [], $item['translation_domain'] . GuidedProjectBuilder::NARRATION_DOMAIN_SUFFIX),
        ];
    }
}
