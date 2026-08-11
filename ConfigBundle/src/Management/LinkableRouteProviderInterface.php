<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implement this interface to expose one of your bundle's own front-end routes (not backed by a SiteBundle Page - e.g. ContactFormBundle's "/contact") as a selectable target for a SiteBundle Menu item (navbar/footer). Lives here (not in SiteBundle) so bundles that don't depend on SiteBundle (ContactFormBundle, ShopBundle, BookBundle...) can still implement it - check readme for usage.
interface LinkableRouteProviderInterface
{
    /**
     * Return [] if none.
     *
     * The key identifies the target and is what a menu item stores ("route:KEY"), the route name itself for a
     * parameter-less route. An entry standing for one row of the contributing bundle's own data (a gallery category,
     * a shop section...) keys itself on that row's id instead and names the 'route' to generate with the 'params' to
     * fill it, its 'label' then being that row's own title, with 'translation_domain' at false so it is shown as is.
     * Such a key carries a literal of your own in front of the id ('gallery_category.' . $id), never a bare number:
     * the id alone is ambiguous the moment two bundles both have a row 42, and the menu item stored for one of them
     * would render the other's target (see ManagementTargetsTestCase, which fails on a numeric key).
     *
     * An entry can also carry a 'picker_label', an already-translated one shown by the back office's target select
     * alone: a row's own title is what the rendered menu item has to read, where the select holds it among every page
     * of the site and needs to say what it is ("Galerie - Paysages" there, "Paysages" in the navbar).
     *
     * @return array<string, array{label: string, translation_domain: string|false, route?: string, params?: array<string, string|int>, picker_label?: string}>
     */
    public function getLinkableRoutes(): array;
}
