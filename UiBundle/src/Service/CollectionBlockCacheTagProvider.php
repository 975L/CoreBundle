<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\CollectionSourceRegistry;

// A "collection" block's html is its source's items, which no Block/Media event ever signals a change of - so its entry is tagged with whatever the source itself declared, and invalidated by the bundle owning those entities (see CollectionSourceProviderInterface). Same for a "collection_entry", which shows one of those very items.
class CollectionBlockCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function __construct(private readonly CollectionSourceRegistry $sourceRegistry)
    {
    }

    public function getCacheTagResolvers(): array
    {
        // Both kinds read the very same two fields off their own data, a "collection_entry" being one item of the listing a "collection" shows whole
        return ['collection' => $this->resolve(...), 'collection_entry' => $this->resolve(...)];
    }

    // Null, i.e. render this block live, in the two cases where a single entry per block would be wrong:
    // - a source declaring no cache tag, which is a source saying it cannot tell when its items change
    // - a block configured with a "detailPage", whose items then carry links built from the page currently being rendered (see CollectionRuntime::buildDetailUrl()): the same block reached under two routes would freeze one page's links into the other's html. Its items are still cached, their own key holding the detail url they were built with - so all that is given up here is the grid wrapper around them
    // - a block drawing its items at random, which a cached entry would freeze into one single draw until the source itself changes, i.e. exactly what asking for a random order says it does not want. Its items keep their own entries all the same, each keyed on the item and not on the draw
    private function resolve(Block $block): ?array
    {
        $data = $block->getData();
        $source = $data['source'] ?? null;

        if (null === $source || null !== ($data['detailPage'] ?? null) || 'random' === ($data['order'] ?? null)) {
            return null;
        }

        $tags = $this->sourceRegistry->cacheTags($source);

        return [] !== $tags ? $tags : null;
    }
}
