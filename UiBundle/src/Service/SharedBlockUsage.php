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
use c975L\UiBundle\Entity\SharedBlock;
use c975L\UiBundle\Repository\BlockRepository;

// Where a shared block is shown, read from the pointers naming it. Block::$data is JSON, so the slug is matched in PHP once the rows are narrowed to the pointer kind - the rule PageRepository follows for the same reason
class SharedBlockUsage
{
    public function __construct(private readonly BlockRepository $blockRepository)
    {
    }

    // The pointers naming this slug, a page in the bin included: restoring it would bring the pointer back
    /** @return Block[] */
    public function findPointers(string $slug): array
    {
        return array_values(array_filter(
            $this->blockRepository->findByKind(SharedBlock::POINTER_KIND),
            static fn (Block $block): bool => $slug === ($block->getData()['slug'] ?? null)
        ));
    }

    // How many pointers name each slug, for the index column and the delete button
    /** @return array<string, int> */
    public function countBySlug(): array
    {
        $counts = [];
        foreach ($this->blockRepository->findByKind(SharedBlock::POINTER_KIND) as $block) {
            $slug = (string) ($block->getData()['slug'] ?? '');
            if ('' !== $slug) {
                $counts[$slug] = ($counts[$slug] ?? 0) + 1;
            }
        }

        return $counts;
    }
}
