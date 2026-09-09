<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Menu\MenuItemInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

// Merges the menus/links contributed by every MenuProvider so bundles sharing the same section appear as one group, with items sorted alphabetically
class MenuBuilder
{
    // Fixed label for the single, merged "links" section, regardless of which bundle contributes links
    private const string LINKS_SECTION_LABEL = 'label.links';
    private const string LINKS_SECTION_TRANSLATION_DOMAIN = 'config';

    // Fixed label and icon for the single, merged "Avancé" submenu, regardless of which bundle contributes an advanced-tier section
    private const string ADVANCED_SUBMENU_LABEL = 'label.menu_advanced';
    private const string ADVANCED_SUBMENU_TRANSLATION_DOMAIN = 'config';
    private const string ADVANCED_SUBMENU_ICON = 'fas fa-screwdriver-wrench';

    // Key (translation_domain.label) of the section every c975L bundle contributing back-office screens shares, kept first whatever the order its bundles were registered in
    private const string PINNED_SECTION_KEY = 'site.label.management';

    public function __construct(
        private readonly iterable $menuProviders,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // One screen's entry in the bar, its action set explicitly rather than left to EasyAdmin: it only defaults to "index" on its own for a CRUD controller, and a section is also where a plain #[AdminRoute] screen belongs (a read-only overview of what the CRUD below it lists, say). Naming it here makes the url resolvable for both, and matches the action OnboardingStepBuilder already generates its own urls with - the tour highlights a step by its href, so the two have to spell it the same way
    private function buildMenuItem(array $menu): MenuItemInterface
    {
        return MenuItem::linkTo($menu['controller'], new TranslatableMessage($menu['label'], [], $menu['translation_domain']), $menu['icon'])
            ->setAction($menu['action'] ?? Action::INDEX)
            // Optional per-menu role (see MenuProviderInterface::getMenus()), falling back on the admin bar every entry used to be given: a screen open to an editor says so itself, and nothing else here can read a CRUD's own setPermission()
            ->setPermission($menu['role'] ?? $this->configService->get('site-role-admin'));
    }

    // Each section is a collapsible submenu rather than a flat header: with a dozen bundles contributing screens, every item at once made a sidebar taller than the viewport. EasyAdmin expands the one holding the current page on its own (see MenuItemMatcher::doMarkExpandedMenuItem()) and its sidebar script keeps a single one open at a time, so the column shows the sections plus the items of wherever you are. The shared "management" section is the exception, kept permanently open as the one every site uses daily
    // Items opting into the 'advanced' tier are collected into one collapsed submenu, rendered last
    // Tier is resolved per item first, several providers commonly sharing one section
    public function getMenuItems(): iterable
    {
        $advancedItems = [];

        foreach ($this->getGroupedMenus() as $key => $section) {
            [$essentialItems, $sectionAdvancedItems] = $this->sectionItems($section);
            $advancedItems = array_merge($advancedItems, $sectionAdvancedItems);

            // Nothing to draw: either the section contributes no items at all, or every one of them moved to "Avancé" - an empty submenu is rendered as nothing by EasyAdmin anyway (see its menu.html.twig)
            if ([] === $essentialItems) {
                continue;
            }

            $submenu = MenuItem::subMenu(new TranslatableMessage($section['label'], [], $section['translation_domain']), $section['icon'] ?? null)
                ->setSubItems($essentialItems);

            // The shared "management" section stays expanded and not collapsible, the accordion never closing it
            if (self::PINNED_SECTION_KEY === $key) {
                $submenu->keepOpen();
            }

            yield $submenu;
        }

        // What is left is every link leaving the admin, gathered in one section whatever bundle contributes it - a link opting into 'advanced' joins the submenu instead, which is why they are resolved before it is yielded rather than inside getLinkItems() below
        $essentialLinks = [];
        foreach ($this->getLinks() as $link) {
            if (!self::leavesTheAdmin($link)) {
                continue;
            }

            $item = $this->buildLinkItem($link);

            if ('advanced' === ($link['tier'] ?? 'essential')) {
                $advancedItems[] = $item;
            } else {
                $essentialLinks[] = $item;
            }
        }

        if ([] !== $advancedItems) {
            yield MenuItem::subMenu(new TranslatableMessage(self::ADVANCED_SUBMENU_LABEL, [], self::ADVANCED_SUBMENU_TRANSLATION_DOMAIN), self::ADVANCED_SUBMENU_ICON)->setSubItems($advancedItems);
        }

        yield from $this->getLinkItems($essentialLinks);
    }

    // One section's own entries, drawn and split in two: the ones it keeps, and the ones joining the "Avancé" submenu. A link to a back-office screen (one with no CRUD of its own, so a route rather than a controller) belongs with the entries of the bundle contributing it, sorted among them by label - only a link leaving the admin is grouped apart, in the "Liens" section
    /**
     * @return array{0: MenuItemInterface[], 1: MenuItemInterface[]}
     */
    private function sectionItems(array $section): array
    {
        $essentialItems = [];
        $advancedItems = [];

        foreach ($this->sortAlphabetically(array_merge(array_values($section['items']), array_values(self::internalLinks($section['links'])))) as $entry) {
            $item = isset($entry['controller']) ? $this->buildMenuItem($entry) : $this->buildLinkItem($entry);

            // A link's tier is its own, a section's default applying to its menus alone (see getMenuSection()) - otherwise two links a provider contributes without a tier of their own would land in different groups, according only to whether they name a target
            if ('advanced' === self::entryTier($entry, $section)) {
                $advancedItems[] = $item;
            } else {
                $essentialItems[] = $item;
            }
        }

        return [$essentialItems, $advancedItems];
    }

    // An item's own tier wins over its section's default
    private static function tier(array $menu, array $section): string
    {
        return $menu['tier'] ?? $section['tier'] ?? 'essential';
    }

    // The tier of a section entry, whether menu or internal link - a menu inherits its section's default (see getMenuSection()), a link never does, so that it is grouped the same way wherever it is drawn
    private static function entryTier(array $entry, array $section): string
    {
        return isset($entry['controller']) ? self::tier($entry, $section) : ($entry['tier'] ?? 'essential');
    }

    // A link opening outside the dashboard says so by naming a target (see MenuProviderInterface::getLinks()), and the "Liens" section is exactly those - a link to a back-office screen names none and sits with its own bundle's entries instead
    // Public: OnboardingStepBuilder splits the same two kinds apart to walk them in the sidebar's own order, and the rule has to be spelled once
    public static function leavesTheAdmin(array $link): bool
    {
        return isset($link['target']);
    }

    // The links of a section that stay inside the admin, the others being gathered in the "Liens" section
    private static function internalLinks(array $links): array
    {
        return array_filter($links, static fn (array $link) => !self::leavesTheAdmin($link));
    }

    // The "Liens" section, rendered last and only when at least one link stayed out of the "Avancé" submenu
    private function getLinkItems(array $links): iterable
    {
        if ([] === $links) {
            return;
        }

        yield MenuItem::section(new TranslatableMessage(self::LINKS_SECTION_LABEL, [], self::LINKS_SECTION_TRANSLATION_DOMAIN));

        yield from $links;
    }

    private function buildLinkItem(array $link): MenuItemInterface
    {
        $item = MenuItem::linkToUrl(
            new TranslatableMessage($link['label'], $link['label_parameters'] ?? [], $link['translation_domain']),
            $link['icon'],
            $this->linkUrl($link)
        );

        // Optional per-link role (see MenuProviderInterface::getLinks()) - unlike CRUD menus above, links can point to routes needing anything from a public page to an editor-only one, so there's no single sensible default; providers not needing gating simply omit the key
        if (isset($link['role'])) {
            $item->setPermission($link['role']);
        }

        // Optional link target (e.g. '_blank' for a link leaving the admin - see MenuProviderInterface::getLinks()) - management.scss shows an external-link glyph automatically for any target="_blank" link, no per-provider styling needed
        if (isset($link['target'])) {
            $item->setLinkTarget($link['target']);
        }

        return $item;
    }

    // "url" (a literal, already-absolute URL) takes precedence when a provider sets it - for a link a provider wants fixed/directly editable rather than derived from a route (e.g. pointing at a specific known deployment on purpose). Otherwise "name" is a route name resolved via linkToUrl()+the plain router, not linkToRoute(): the latter resolves through EasyAdmin's own AdminUrlGenerator (see MenuFactory), which assumes the route is one of the dashboard's own registered actions and wraps it as "/management?routeName=...&..." - correct only for a route reachable *through* the dashboard. A link to a plain, unrelated route (e.g. a consuming app's own public page) needs its real path instead, which the plain router already gives via generate() - this works uniformly for both cases, since a dashboard-registered route's own real path resolves correctly through generate() too. A route-based link with a "target" is leaving the admin entirely - resolved as a full absolute URL (scheme+host), not just a path, since it's meant to stand on its own once opened in a new tab. Still generated from the current request each time, never hardcoded, so it stays correct across dev/staging/prod or any future domain change - only the route itself is a fixed reference, the same way a same-tab link already works.
    private function linkUrl(array $link): string
    {
        if (isset($link['url'])) {
            return $link['url'];
        }

        $referenceType = isset($link['target'])
            ? UrlGeneratorInterface::ABSOLUTE_URL
            : UrlGeneratorInterface::ABSOLUTE_PATH;

        return $this->urlGenerator->generate($link['name'], [], $referenceType);
    }

    // Returns all menus, merged across providers and sorted alphabetically
    public function getMenus(): array
    {
        return $this->sortAlphabetically(ProviderMerger::merge($this->menuProviders, fn (MenuProviderInterface $provider) => $provider->getMenus()));
    }

    // Returns all links, merged across providers and sorted alphabetically
    public function getLinks(): array
    {
        return $this->sortAlphabetically(ProviderMerger::merge($this->menuProviders, fn (MenuProviderInterface $provider) => $provider->getLinks()));
    }

    // Every menu and every link staying inside the admin, flattened into the same essential-then-advanced-across-sections order the sidebar itself renders (see getMenuItems()) - every section's essential entries first (in provider/section order, alphabetical within each), then every section's advanced ones grouped together at the end (the collapsed "Avancé" submenu), rather than getMenus()'s plain alphabetical merge across all of them. An internal link is drawn among its section's entries, so it is walked there too; only a link leaving the admin is left out, being drawn last in the "Liens" section. Used by OnboardingStepBuilder so tour steps walk the sidebar in the order a user actually sees it
    public function getOrderedMenus(): array
    {
        $essential = [];
        $advanced = [];

        foreach ($this->getGroupedMenus() as $section) {
            // Merged and sorted exactly as getMenuItems() does it, an internal link sitting among the entries of the bundle contributing it
            foreach ($this->sortAlphabetically(array_merge($section['items'], self::internalLinks($section['links']))) as $key => $entry) {
                if ('advanced' === self::entryTier($entry, $section)) {
                    $advanced[$key] = $entry;
                } else {
                    $essential[$key] = $entry;
                }
            }
        }

        return $essential + $advanced;
    }

    // Groups the menus by section, so providers sharing the same section (label + translation_domain) are merged - a provider's links are carried along too, those staying inside the admin being drawn in that same section (see getMenuItems())
    private function getGroupedMenus(): array
    {
        $sections = [];
        foreach ($this->menuProviders as $provider) {
            $section = $provider->getMenuSection();
            $key = $section['translation_domain'] . '.' . $section['label'];
            $sections[$key] ??= $section + ['items' => [], 'links' => []];
            $sections[$key]['items'] = array_merge($sections[$key]['items'], $provider->getMenus());
            $sections[$key]['links'] = array_merge($sections[$key]['links'], $provider->getLinks());
        }

        foreach ($sections as &$section) {
            $section['items'] = $this->sortAlphabetically($section['items']);
        }
        unset($section);

        return $this->sortSections($sections);
    }

    // Sections in the order the sidebar shows them: the shared "management" one first, the rest alphabetically by translated label - the same rule their items already follow. Left unsorted they came out in tagged-iterator order, i.e. the order the bundles happen to be registered in, so a bundle installed later always landed at the bottom of the sidebar, right above the "Avancé" submenu. Sorting here rather than in getMenuItems() keeps getOrderedMenus(), and with it the onboarding tour, walking the sidebar in the order it is actually drawn
    private function sortSections(array $sections): array
    {
        uksort($sections, function (string $a, string $b) use ($sections) {
            if (self::PINNED_SECTION_KEY === $a || self::PINNED_SECTION_KEY === $b) {
                return self::PINNED_SECTION_KEY === $a ? -1 : 1;
            }

            return strcasecmp(
                $this->translator->trans($sections[$a]['label'], [], $sections[$a]['translation_domain']),
                $this->translator->trans($sections[$b]['label'], [], $sections[$b]['translation_domain']),
            );
        });

        return $sections;
    }

    // Sorts an array of menus/links by their translated label, keeping their keys - an item can set 'pinned' => true (links only, see MenuProviderInterface::getLinks()) to always sort after every non-pinned item, regardless of label (e.g. a "visit the site" link meant to stay at the very bottom of the links section)
    private function sortAlphabetically(array $items): array
    {
        uasort($items, function (array $a, array $b) {
            $pinnedA = $a['pinned'] ?? false;
            $pinnedB = $b['pinned'] ?? false;
            if ($pinnedA !== $pinnedB) {
                return $pinnedA <=> $pinnedB;
            }

            return strcasecmp(
                $this->translator->trans($a['label'], [], $a['translation_domain']),
                $this->translator->trans($b['label'], [], $b['translation_domain']),
            );
        });

        return $items;
    }
}
