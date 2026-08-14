<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement to have a cache of your own emptied along with the block render cache - the dashboard's "Clear the block cache" tile, a legal model being customized, and every "bin/console cache:clear" all go through BlockCacheInvalidator::invalidateAll(), which is what calls this (auto-discovered the same way as BlockFixtureProviderInterface, no tag needed).
// What belongs here is a cache holding rendered output or query results whose key carries no version of the code that produced it: a Twig "{% cache %}" fragment around an app's own component, a Doctrine result cache on the lists an index shows. Those go stale on a release that changes nothing in the database, which is precisely what the tile - and cache:clear - exist to settle.
// An implementation must not reach back into BlockCacheInvalidator, which is the service calling it.
interface CacheInvalidatorInterface
{
    public function invalidate(): void;
}
