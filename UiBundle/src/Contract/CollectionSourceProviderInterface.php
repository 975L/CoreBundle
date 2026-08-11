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
    /**
     * "detail" (optional) lets a Page holding this source's "collection" block also serve per-item detail URLs (/pages/{page}/{slug}), see PageController::resolveCollectionDetail() - null falls through to a 404, otherwise a plain array of template variables for the Page's "twig_content" templatePath, by convention including a 'title' key.
     *
     * @return array<string, array{label: string, items: callable(?int): iterable<CollectionItem>, detail?: ?callable(string): ?array<string, mixed>}> unique source key (e.g. "site.collection.projects") => source
     */
    public function getSources(): array;
}
