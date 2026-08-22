<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\FavoriteItemProviderInterface;
use c975L\UiBundle\Model\CollectionItem;

class FavoriteItemRegistry
{
    /** @var FavoriteItemProviderInterface[] */
    private array $providers = [];

    public function addProvider(FavoriteItemProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * A whole list resolved in the order it was stored, one query per kind of thing.
     *
     * @param array<string, int[]> $ids owner type => its ids, newest first (see FavoriteRepository::findIdsByHolder())
     *
     * @return list<array{ownerType: string, ownerId: int, item: CollectionItem}> the kinds nobody resolves, and the ids nobody has anything to show for, are simply absent
     */
    public function resolve(array $ids): array
    {
        $resolved = [];

        foreach ($ids as $ownerType => $ownerIds) {
            $provider = $this->providerFor($ownerType);

            // Nothing implements this kind any more - the bundle owning it was removed, or never shipped a provider. The entries stay in the table, where they will be drawn again the day it comes back
            if (null === $provider) {
                continue;
            }

            $items = $provider->getItems($ownerType, $ownerIds);

            // Walked in the order asked for and not in the order answered: the provider queries by a set of ids, which no database returns in any particular order, where the list is shown newest first
            foreach ($ownerIds as $ownerId) {
                if (isset($items[$ownerId])) {
                    $resolved[] = ['ownerType' => $ownerType, 'ownerId' => $ownerId, 'item' => $items[$ownerId]];
                }
            }
        }

        return $resolved;
    }

    private function providerFor(string $ownerType): ?FavoriteItemProviderInterface
    {
        $matching = array_values(array_filter(
            $this->providers,
            static fn (FavoriteItemProviderInterface $provider): bool => $provider->supports($ownerType)
        ));

        // Fails loudly rather than letting one provider silently win and the other become unreachable - same reading as BlockOwnerRegistry's
        if (\count($matching) > 1) {
            throw new \LogicException(sprintf('Several FavoriteItemProviderInterface providers support ownerType "%s": %s.', $ownerType, implode(', ', array_map(static fn (object $provider): string => $provider::class, $matching))));
        }

        return $matching[0] ?? null;
    }
}
