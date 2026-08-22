<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Model\CollectionItem;

// Implement to turn the rows a wishlist holds back into something a page can draw, without this bundle ever depending on the bundle owning them - same auto-discovery mechanism as CollectionSourceProviderInterface, no tag needed.
// A Favorite stores a name and an id and nothing else, so a list is unreadable until whoever owns that name resolves it. A kind nobody implements simply does not show: an entry pointing at a bundle the site has since removed is dropped rather than drawn empty.
interface FavoriteItemProviderInterface
{
    /**
     * @param string $ownerType the short stable string the implementing bundle stores its favourites under ("shop_product", "book"...), round-tripped verbatim
     */
    public function supports(string $ownerType): bool;

    /**
     * The things behind those ids, as the very CollectionItem the "collection_item" card already draws - so a wishlist looks like the rest of the site without a template of its own.
     *
     * Asked for the whole page at once: a list of thirty entries must not run thirty queries.
     *
     * Whatever the visitor may no longer see - a draft, something trashed, something withdrawn from sale - is left out rather than returned: a list is public reading, and this is the provider's own call, being the only one that knows what "published" means for its kind of thing.
     *
     * @param int[] $ownerIds
     *
     * @return array<int, CollectionItem> keyed by owner id, ids with nothing to show being absent
     */
    public function getItems(string $ownerType, array $ownerIds): array;
}
