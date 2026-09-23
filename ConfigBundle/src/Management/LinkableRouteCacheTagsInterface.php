<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Management;

// Implemented beside LinkableRouteProviderInterface by a provider listing one entry per row of its own data: the tags its bundle already empties when one of those rows is saved. The registry then keeps the entries in the cache under them, and a menu item pointing at one of them is cached too (see SiteBundle's MenuExtension::getMenuLinkCacheTags) - a provider not implementing it is read live, as before
interface LinkableRouteCacheTagsInterface
{
    /** @return string[] */
    public function getLinkableRouteCacheTags(): array;
}
