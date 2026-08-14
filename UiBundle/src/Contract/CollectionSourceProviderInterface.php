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
     * "count" (optional) is the source's total, asked for without building every item (see CollectionType's own help text).
     *
     * "detail" (optional) lets a Page holding this source's "collection" block also serve per-item detail URLs (/pages/{page}/{slug}), see PageController::resolveCollectionDetail() - null falls through to a 404, otherwise a plain array of template variables for the Page's "twig_content" templatePath, by convention including a 'title' key.
     *
     * "cacheTags" (optional) is what makes this source's items cacheable at all: they are applied to every entry built from it, so the provider's own bundle only has to invalidate them - one Doctrine listener on the entities behind the source - for every "collection" block showing them to render fresh. Declaring none is not a failure, it is a source saying it cannot tell when its items change: it is then rendered live on every request, which is the safe reading of "no way to invalidate". Nothing forbids several sources sharing one tag, and the sources cut out of the same entity normally should (a "characters" source and a "characters of one faction" source both go stale on the same edit).
     *
     * "itemTemplate" (optional) is the template each of this source's items is rendered by, for the sources whose items are not what the built-in card draws - a cover in portrait beside its text, a panel of its own. It is included with the item's whole data (its own "data" keys first, see CollectionItem, then title/content/url/imageUrl/detailUrl/variant), so a source hands its entity over in "data" and the template it names - a component of the app that owns both - reads it under whatever name it expects. Declaring none keeps the "collection_item" card every source has always been rendered by, and nothing else changes. A source naming a template answers for its caching too: this path bypasses the entry cache above, the template being free to hold its own {% cache %} against the very entity it draws.
     *
     * @return array<string, array{label: string, count?: callable(): int, items: callable(?int): iterable<CollectionItem>, detail?: ?callable(string): ?array<string, mixed>, cacheTags?: string[], itemTemplate?: string}> unique source key (e.g. "site.collection.projects") => source
     */
    public function getSources(): array;
}
