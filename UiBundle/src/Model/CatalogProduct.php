<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// A product another bundle's catalog hands the shop to write (see ProductCatalogWriterInterface) - BookBundle's book with the files its editions are sold as. key is the catalog's own stable name for it, never shown: the shop finds the product it wrote under it, and creates it when there is none
final class CatalogProduct
{
    /** @param list<CatalogProductItem> $items */
    public function __construct(
        public readonly string $key,
        public readonly string $title,
        public readonly string $description,
        public readonly array $items,
        // The absolute path of the picture the product gets when it is created, null for none
        public readonly ?string $coverPath = null,
    ) {
    }
}
