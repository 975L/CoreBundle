<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Management;

use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Repository\SharedBlockRepository;

// Lets a block of a shared block be dragged into a container of the same run, as on a page (see BlockMoveRowAttrBuilder)
class SharedBlockOwnerResolver implements BlockOwnerResolverInterface
{
    public const string TYPE = 'shared_block';

    public function __construct(private readonly SharedBlockRepository $sharedBlockRepository)
    {
    }

    public function supports(string $ownerType): bool
    {
        return self::TYPE === $ownerType;
    }

    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
    {
        return self::TYPE === $ownerType ? $this->sharedBlockRepository->find($ownerId) : null;
    }
}
