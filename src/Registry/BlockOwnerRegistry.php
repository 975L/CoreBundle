<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BlockOwnerResolverInterface;
use c975L\UiBundle\Contract\HasBlocksInterface;

class BlockOwnerRegistry
{
    /** @var BlockOwnerResolverInterface[] */
    private array $providers = [];

    public function addProvider(BlockOwnerResolverInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    public function find(string $ownerType, int $ownerId): ?HasBlocksInterface
    {
        $matching = array_values(array_filter(
            $this->providers,
            fn (BlockOwnerResolverInterface $provider): bool => $provider->supports($ownerType)
        ));

        // Fails loudly rather than letting one resolver silently win and the other become unreachable
        if (\count($matching) > 1) {
            throw new \LogicException(sprintf('Several BlockOwnerResolverInterface providers support ownerType "%s": %s.', $ownerType, implode(', ', array_map(static fn (object $provider): string => $provider::class, $matching))));
        }

        if ([] === $matching) {
            return null;
        }

        return $matching[0]->find($ownerType, $ownerId);
    }
}
