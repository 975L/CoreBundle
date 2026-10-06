<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// One item of a CatalogProduct - a file sold at a price - or, read back from the shop for a one-shot import, one of its items holding a file (see ProductCatalogWriterInterface::itemsWithFile(), which fills id and the product's slug and title)
final class CatalogProductItem
{
    /** @param array<string, string> $description */
    public function __construct(
        public readonly ?string $key,
        public readonly string $title,
        // The absolute path of the file sold, copied into the shop's own private directory
        public readonly string $filePath,
        // Tax included and in cents, null leaving the shop's price as it is
        public readonly ?int $price = null,
        public readonly string $currency = 'EUR',
        public readonly ?int $id = null,
        public readonly ?string $slug = null,
        public readonly ?string $productSlug = null,
        public readonly ?string $productTitle = null,
        // What the item's sheet says of the file, locale => text: the shop's default locale written on the row, the others as its translations, its title for want of one
        public readonly array $description = [],
    ) {
    }
}
