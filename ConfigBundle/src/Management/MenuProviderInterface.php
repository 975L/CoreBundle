<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

interface MenuProviderInterface
{
    /**
     * 'tier' is optional - omit it (or return 'essential') for the current behavior. It only sets the *default* for this provider's own items in getMenus() below - several providers commonly share one section (e.g. ConfigBundle/SiteBundle/UiBundle all merge into the same "management" section), so a section-level 'tier' never affects another provider's items merged into the same section, and an item can still override it individually.
     *
     * 'icon' is optional, the icon drawn next to the section's own caption in the sidebar - a section is rendered as a collapsible submenu (see MenuBuilder::getMenuItems()), so it carries an icon the way its items do; omit it for a caption with none. Like 'tier', it belongs to whichever provider is merged first when several share one section.
     *
     * @return array{label: string, translation_domain: string, tier?: 'essential'|'advanced', icon?: string}
     */
    public function getMenuSection(): array;

    // 'controller' is a CRUD controller whose index the entry opens, or a plain controller carrying an #[AdminRoute] index() (or the method 'action' names) for a screen belonging next to the CRUDs it reads - anything outside the dashboard is a link (see getLinks()). 'role' defaults to 'site-role-admin' and should match the bar the screen itself states: too high hides an entry its screen would answer, too low walks the user and the onboarding tour into a 403. 'tier' defaults to getMenuSection()'s, 'advanced' tucking the item into the collapsed "Avancé" submenu. 'description' reuses the screen's own explanatory text for its tour step, and 'narration' is that step spoken by the films of the back office (resolved in the item's $translation_domain suffixed with "_narration"), both optional. 'creatable' is false for a CRUD listing what happened rather than what an admin makes (payments, baskets, 404s, imported reviews), which the unused features panel must not read as a feature left unused (see UnusedFeatureBuilder)
    /** @return array<string, array{controller: class-string, label: string, translation_domain: string, icon: string, action?: string, role?: string, tier?: 'essential'|'advanced', description?: string, narration?: string, creatable?: bool}> slug => menu item */
    public function getMenus(): array;

    /**
     * Links to routes (not EasyAdmin CRUD controllers), merged by MenuBuilder into a single "links" section; return [] if none. Each entry needs either a 'name' (a route name, resolved to its real URL through the app's own router - the usual case) or a 'url' (a literal, already-absolute URL used as-is, no route resolution at all - for a link a provider wants fixed/directly editable, e.g. a specific known deployment). 'url' takes precedence when both are set. Each entry may set an optional 'role' key (e.g. 'ROLE_EDITOR') to hide the link from users lacking it - omit it for links to routes with no access restriction of their own (e.g. a public page). Each entry may also set an optional 'target' key (e.g. '_blank') for a link leaving the admin entirely (e.g. a public showcase page) - MenuBuilder shows an external-link glyph automatically for any such link, and (for a 'name'-based link only) resolves it to a full absolute URL instead of a relative path. That key is also what decides where the link is drawn: naming a target puts it in the shared "Liens" section, gathering everything that leaves the back office, while a link naming none is a back-office screen with no CRUD of its own (a health check, an import) and is drawn inside this provider's own section, sorted among its menu entries by label. An optional 'pinned' bool key forces the link to sort after every non-pinned link regardless of its label (e.g. a "visit the site" link meant to always stay at the very bottom of the links section). An optional 'label_parameters' array is passed through to the translator alongside 'label' (e.g. ['%name%' => $siteName], for a translated label embedding a runtime value) - omit it for a plain translation key with no placeholder, the usual case. Also accepts the same optional 'description' and 'narration' keys as getMenus() above, for the onboarding tour - a link whose 'role' the current user lacks is excluded from the tour entirely (see OnboardingStepBuilder), since its sidebar target isn't rendered for them either.
     *
     * An optional 'tier' key works like getMenus()' one: 'advanced' moves the link into the same collapsed "Avancé" submenu the advanced CRUD items go to, instead of the "Liens" section (e.g. a screen used a couple of times a year). The "Liens" section disappears entirely if every link opted into it.
     *
     * @return array<string, array{name?: string, url?: string, label: string, translation_domain: string, icon: string, role?: string, target?: string, tier?: 'essential'|'advanced', pinned?: bool, label_parameters?: array<string, string>, description?: string, narration?: string}> slug => link (slugs are merged across every provider, so keep them bundle-specific)
     */
    public function getLinks(): array;
}
