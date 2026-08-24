<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

interface ShortcutProviderInterface
{
    // Shared categories, so a provider grouping its shortcut alongside another bundle's (e.g. c975L\SiteBundle's export tables shortcut and c975L\ConfigBundle's own SQL/sync exports) references the same constant rather than a hand-typed label that could drift
    public const CATEGORY_EXPORT = ['label' => 'label.shortcuts_category_export', 'translation_domain' => 'config'];
    public const CATEGORY_MAINTENANCE = ['label' => 'label.shortcuts_category_maintenance', 'translation_domain' => 'config'];
    public const CATEGORY_SITE = ['label' => 'label.shortcuts_category_site', 'translation_domain' => 'config'];
    // The row an admin scans to know what the site currently has switched on or off (maintenance, registration, a bundle's test mode): every tile in it toggles one thing, and the tile of a state worth signalling is painted as a warning by the template
    public const CATEGORY_TOGGLE = ['label' => 'label.shortcuts_category_toggle', 'translation_domain' => 'config'];

    /**
     * 'route' must accept a POST request and check its own CSRF token (csrf_token(route) in the template), unless the shortcut sets 'method' => 'GET' - reserved for a tile opening a page rather than acting (see ConfigPruneController), the tile then being rendered as a plain link with no token; 'active' says the thing the tile toggles is currently on, so clicking it turns that thing off, a one-shot action (and anything the tile does not toggle) always returning false. 'warning' is optional and says the current state deserves an admin's attention - the template paints those tiles as a warning; left out, it follows 'active' (ShortcutBuilder fills it), which suits a thing whose "on" is the state to signal (maintenance, a test mode) and not one whose "off" is (an open registration being what a site normally does, its tile passing 'warning' => false when open). 'role' is optional - omit it for a shortcut with no access restriction of its own, set it (e.g. 'ROLE_SUPER_ADMIN') to hide the tile from users lacking it. 'category' is optional too (one of the CATEGORY_* constants above, or a custom ['label' => string, 'translation_domain' => string] pair, both untranslated - translated once by ShortcutBuilder): shortcuts sharing the same one (across bundles) are grouped under one heading, on a row of their own; omit it to fall into the generic "Other" category.
     *
     * @return list<array{label: string, icon: string, route: string, active: bool, warning?: bool, role?: string, category?: array{label: string, translation_domain: string}, method?: 'GET'|'POST'}>
     */
    public function getShortcuts(): array;
}
