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
     * @return array{label: string, translation_domain: string, tier?: 'essential'|'advanced'}
     */
    public function getMenuSection(): array;

    /**
     * 'controller' is usually a CRUD controller, whose index action the entry opens. It can also be a plain controller carrying an #[AdminRoute] method, for a screen that belongs in a section next to the CRUD items it reads from rather than in the "links" section below (e.g. an overview of what a CRUD lists) - such a controller needs a method named index() for the entry to resolve with no further work, or an explicit 'action' naming the method to open. Anything outside the dashboard (a public page, another app) is a link, not a menu - see getLinks() below.
     *
     * 'role' is optional and defaults to 'site-role-admin', which every entry used to be given: set it to the bar the entry's own screen states (its CRUD's setPermission(Action::INDEX, ...), the #[AdminRoute] action's denyAccessUnlessGranted()) for a screen open below that - a media library or a redirects list an editor is meant to reach. Too high and the entry is missing from a sidebar its screen would have answered; too low and it leads that user to a 403, and the onboarding tour walks them to it (see OnboardingStepBuilder, which skips a menu the same way it skips a link).
     *
     * 'tier' is optional, defaults to the provider's own getMenuSection() 'tier' (itself defaulting to 'essential') - set it on an individual item to move just that one to the collapsed "Avancé" submenu while its section keeps its other items at the top level (e.g. a bundle keeping "Pages" essential but tucking away "Redirections"). 'description' is optional too, a one-line "what is this for" sentence (same $translation_domain) reused as-is from the item's own page rather than a separate onboarding-only string - every item the current user has the 'role' for gets a step in the onboarding tour (see OnboardingStepBuilder) regardless, one without a description simply shows its label with no explanatory text. 'narration' is optional as well, what that tour step sounds like when it is spoken rather than read - a full sentence naming where the entry sits and what it opens ("dans la colonne de gauche, ouvrez le menu Pages"), where 'label' is a sidebar caption and 'description' the line drawn under it. It is read by the films of the back office (see app:video:narrate in bundles.975l.com) and drawn nowhere; a step without one falls back to its label and description, which reads as a sidebar entry spoken aloud. Like a guided project's, it is resolved in the item's own $translation_domain suffixed with "_narration" (an item served by "site" reads its narrations in "site_narration").
     *
     * @return array<string, array{controller: class-string, label: string, translation_domain: string, icon: string, action?: string, role?: string, tier?: 'essential'|'advanced', description?: string, narration?: string}> slug => menu item
     */
    public function getMenus(): array;

    /**
     * Links to routes (not EasyAdmin CRUD controllers), merged by MenuBuilder into a single "links" section; return [] if none. Each entry needs either a 'name' (a route name, resolved to its real URL through the app's own router - the usual case) or a 'url' (a literal, already-absolute URL used as-is, no route resolution at all - for a link a provider wants fixed/directly editable, e.g. a specific known deployment). 'url' takes precedence when both are set. Each entry may set an optional 'role' key (e.g. 'ROLE_EDITOR') to hide the link from users lacking it - omit it for links to routes with no access restriction of their own (e.g. a public page). Each entry may also set an optional 'target' key (e.g. '_blank') for a link leaving the admin entirely (e.g. a public showcase page) - MenuBuilder shows an external-link glyph automatically for any such link, and (for a 'name'-based link only) resolves it to a full absolute URL instead of a relative path. An optional 'pinned' bool key forces the link to sort after every non-pinned link regardless of its label (e.g. a "visit the site" link meant to always stay at the very bottom of the links section). An optional 'label_parameters' array is passed through to the translator alongside 'label' (e.g. ['%name%' => $siteName], for a translated label embedding a runtime value) - omit it for a plain translation key with no placeholder, the usual case. Also accepts the same optional 'description' and 'narration' keys as getMenus() above, for the onboarding tour - a link whose 'role' the current user lacks is excluded from the tour entirely (see OnboardingStepBuilder), since its sidebar target isn't rendered for them either.
     *
     * An optional 'tier' key works like getMenus()' one: 'advanced' moves the link into the same collapsed "Avancé" submenu the advanced CRUD items go to, instead of the "Liens" section (e.g. a screen used a couple of times a year). The "Liens" section disappears entirely if every link opted into it.
     *
     * @return array<string, array{name?: string, url?: string, label: string, translation_domain: string, icon: string, role?: string, target?: string, tier?: 'essential'|'advanced', pinned?: bool, label_parameters?: array<string, string>, description?: string, narration?: string}> slug => link (slugs are merged across every provider, so keep them bundle-specific)
     */
    public function getLinks(): array;
}
