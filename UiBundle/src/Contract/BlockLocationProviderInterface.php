<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Entity\Block;

// Lets any bundle owning blocks say where a given Block sits, for the screens listing blocks across the whole site rather than within one owner's form (see LegalModelController and LegalModelDriftHealthCheckProvider). UiBundle itself knows nothing of pages: without a provider the block is simply listed with no location, which is exactly what an app installing ShopBundle without SiteBundle gets. Implement this and the service is auto-discovered by BlockLocationProviderPass - see Readme.
interface BlockLocationProviderInterface
{
    /**
     * @param Block[] $blocks the Block rows to resolve, already loaded by the caller
     *
     * @return array<int, array{label: string, url: ?string, published: bool}> locations keyed by Block id, only for blocks this provider recognizes as its own - "url" being the public address the block is reachable at, null when it has none (unpublished owner, site url not configured yet)
     */
    public function getLocations(array $blocks): array;
}
