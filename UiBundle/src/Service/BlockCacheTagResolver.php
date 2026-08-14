<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Registry\BlockRegistry;

// The whole "can this block be cached, and under which tags" decision, in one place: BlockExtension only asks, and BlockCacheTagRegistry stays the dumb kind => resolver map the providers fill.
// It exists because that decision stopped being a per-kind flag the day containers became cacheable: a container's entry holds its slots' rendered html inline, so it inherits both their tags and their right to be cached at all.
class BlockCacheTagResolver
{
    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly BlockCacheTagRegistry $cacheTagRegistry,
    ) {
    }

    /**
     * Every extra cache tag this block's own entry has to carry, or null when it must not be cached at all.
     *
     * @return string[]|null
     */
    public function resolve(Block $block): ?array
    {
        return $this->resolveWithSeen($block, []);
    }

    /**
     * The same, carrying the ids already met on the way down so a cycle cannot spin here forever.
     *
     * @param array<int, true> $seen
     *
     * @return string[]|null
     */
    private function resolveWithSeen(Block $block, array $seen): ?array
    {
        $kind = $block->getKind();

        if (null === $kind || !$this->registry->has($kind) || !$this->registry->isCacheable($kind)) {
            return null;
        }

        $tags = $this->cacheTagRegistry->getExtraTags($block);

        if (null === $tags || !$this->registry->isContainer($kind)) {
            return $tags;
        }

        return $this->withSlotTags($block, $tags, $seen);
    }

    /**
     * A container's slots, whose html its own entry holds verbatim: each contributes its "block_{id}" tag (so editing that slot re-renders the container too, see BlockCacheInvalidationListener) plus whatever its own kind added, and any one of them declining to be cached takes the container down with it - one form and its csrf token, one twig_content varying with the collection item being rendered, and the container's html stops being reusable.
     * Recursive, a nested container inlining its own slots the same way.
     *
     * @param array<int, true> $seen
     *
     * @return string[]|null
     */
    private function withSlotTags(Block $block, array $tags, array $seen): ?array
    {
        $id = $block->getId();
        if (null !== $id) {
            $seen[$id] = true;
        }

        foreach ($block->getSlots() as $slot) {
            $slotKind = $slot->getKind();

            // A slot saved without a kind, or whose kind is no longer registered, renders as nothing at all (see BlockExtension::renderHtml()) - it puts nothing into the container's html either, so it has nothing to invalidate on
            if (null === $slotKind || !$this->registry->has($slotKind)) {
                continue;
            }

            // Guarded rather than trusted, same as BlockCacheInvalidationListener::tagsUpTheChain(): a block already met on the way down is one an imported or hand-edited row put back under itself, and descending into it again would spin here forever. Its tag is already on the entry of the container that first held it, so skipping it loses nothing
            $slotId = $slot->getId();
            if (null !== $slotId && isset($seen[$slotId])) {
                continue;
            }

            $slotTags = $this->resolveWithSeen($slot, $seen);

            // A slot still to be flushed has no id yet, so there would be no tag to reach its half of the container's html with
            if (null === $slotTags || null === $slotId) {
                return null;
            }

            $tags = [...$tags, 'block_' . $slotId, ...$slotTags];
        }

        return $tags;
    }
}
