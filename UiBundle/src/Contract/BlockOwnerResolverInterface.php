<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Makes a bundle's own HasBlocksInterface entity reachable by BlockMoveController without a dependency on it.
 */
interface BlockOwnerResolverInterface
{
    /**
     * @param string $ownerType a short stable string chosen by the implementing bundle, round-tripped verbatim
     */
    public function supports(string $ownerType): bool;

    /**
     * Returns null when this resolver owns $ownerType but has no entity for $ownerId.
     */
    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface;
}
