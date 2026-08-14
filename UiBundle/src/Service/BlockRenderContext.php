<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// Armed for the whole of a render that must neither read from nor write to the block cache - an editor's page preview (see SiteBundle's PageController::preview()).
// Two reasons rather than one: the preview has to show what was just saved, and its own render is not the public one. A "collection" block builds its items' detail links against the preview route (see CollectionRuntime::buildDetailUrl()), so a preview writing into the cache would hand every later visitor links into an editor-only route.
class BlockRenderContext
{
    private bool $cacheDisabled = false;

    public function disableCache(): void
    {
        $this->cacheDisabled = true;
    }

    public function isCacheDisabled(): bool
    {
        return $this->cacheDisabled;
    }
}
