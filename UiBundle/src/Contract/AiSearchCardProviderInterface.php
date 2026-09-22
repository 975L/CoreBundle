<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement to show a page the site search answered from as a card of your own rather than a plain link - a product with its picture, its price and its basket button - without this bundle depending on yours. Auto-discovered, no tag needed (see AiSearchCardProviderPass)
interface AiSearchCardProviderInterface
{
    // The cards of the urls this bundle owns, rendered by its own templates. Asked for all the sources of an answer at once, so a handful of products is one query. Rendered live on each answer, never stored: the price, the stock and whether the thing is still on sale are read at the moment the visitor reads them. Leave out what the visitor may no longer see - that url then stays a plain link, or is dropped by nothing
    /**
     * @param list<string> $urls absolute, as the index holds them
     *
     * @return array<string, string> html keyed by url, urls this bundle doesn't own being absent
     */
    public function renderCards(array $urls): array;
}
