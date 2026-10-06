<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Model\CatalogProduct;
use c975L\UiBundle\Model\CatalogProductItem;

// Implemented by the shop, for another bundle's catalog to sell what it holds - BookBundle writes each book's files into it. Declared here so that neither knows the other: the catalog asks for this interface, optional, and the shop answers it when it is installed. The files are copied, never shared: each bundle keeps its own, as it would without the other
interface ProductCatalogWriterInterface
{
    // Finds the product written under $product->key, or creates it when it has items - title, description and picture are only written then, the shop's editor owning them afterwards. Each item is found under its own key or created, its file replaced when it changed and its price set; an item written earlier under a key the product no longer lists is hidden, never deleted, orders pointing at it
    public function write(CatalogProduct $product): void;

    // Every item holding a file, with its id, its slug and its product's - what a one-shot import into the catalog reads
    /** @return list<CatalogProductItem> */
    public function itemsWithFile(): array;

    // Stamps the keys a one-shot import matched an item and its product to, so that the next write() finds them rather than creating them anew
    public function setKeys(int $itemId, string $productKey, string $itemKey): void;
}
