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

// Implement to expose a queryable collection of another bundle's own entities (books, products, projects...) to the "collection" block, without that block ever depending on the owning bundle - same auto-discovery mechanism as BlockFixtureProviderInterface, no tag needed.
interface CollectionSourceProviderInterface
{
    // "count" (optional) is the source's total, asked without building every item. "detail" (optional) serves per-item urls (/pages/{page}/{slug}, see PageController::resolveCollectionDetail()), null being a 404 and an array the variables of the Page's "twig_content" template, 'title' included. "cacheTags" (optional) makes the items cacheable, the owning bundle emptying them from one Doctrine listener - none declared, the source is rendered live, and sources cut from one entity should share one tag. "itemTemplate" (optional) renders each item with its whole data ("data" keys first, see CollectionItem), none keeping the "collection_item" card - its items are cached under the source's cacheTags like any other, and an item with no slug is keyed on data["id"]
    /** @return array<string, array{label: string, count?: callable(): int, items: callable(?int): iterable<CollectionItem>, detail?: ?callable(string): ?array<string, mixed>, cacheTags?: string[], itemTemplate?: string}> unique source key (e.g. "site.collection.projects") => source */
    public function getSources(): array;
}
