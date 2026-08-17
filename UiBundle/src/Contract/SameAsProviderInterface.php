<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement to add the urls of the profiles naming this very business elsewhere - a Google listing, a social account, a directory entry - to the "sameAs" of the graph a "contact_details" block publishes. That property is what ties the site to those profiles as one entity, where "hasMap" only says a map of the place exists; the bundle owning the profiles is the one that knows their urls, hence a provider rather than a field here (same auto-discovery as BlockFixtureProviderInterface, no tag needed).
interface SameAsProviderInterface
{
    // Absolute urls, in whatever order; the registry drops the empty ones and de-duplicates across providers
    /** @return string[] */
    public function getSameAs(): array;
}
