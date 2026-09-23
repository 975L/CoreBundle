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

    // Null, i.e. render this block live, in the three cases where a single entry per block would be wrong: a source declaring no cache tag, saying it cannot tell when its items change; a "detailPage", whose item links are built from the page being rendered (see CollectionRuntime::buildDetailUrl()), the items themselves staying cached; a random order, which one entry would freeze into a single draw, the whole source's html being kept and drawn from at each render instead (see CollectionRuntime::renderRandomItems())
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
