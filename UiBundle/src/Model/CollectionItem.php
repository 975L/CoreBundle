<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Model;

// One entry of a CollectionSourceProviderInterface source - the "collection" block renders each as a never-persisted "collection_item" Block (see Collection.html.twig). imageUrl is an already-resolved URL: each provider is responsible for its own image storage (a real attached Media, a Vich field on its own entity, anything) and hands back a plain string, never a Media/entity reference. slug is only needed to link the item's title to its detail page: set it to whatever the source's own "detail" callable expects (see CollectionSourceProviderInterface) - left null, the title renders as plain text, same as a source with no "detail" capability at all
final class CollectionItem
{
    /**
     * @param array<string, mixed> $data Whatever else the source has to say about this item, merged into the rendered block's own data the same way a stored Block carries its editor's input - which is what keeps this constructor from growing a property per prop the Card happens to accept ("eyebrow", "rating", "stats", "class", and the next one after them). Keys the item's template knows are rendered, the rest are simply carried; the fixed arguments above always win over a key of the same name (see CollectionRuntime::renderItems())
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?string $imageUrl = null,
        public readonly ?string $url = null,
        public readonly ?string $buttonLabel = null,
        public readonly ?string $buttonIcon = null,
        public readonly ?string $slug = null,
        public readonly array $data = [],
    ) {
    }
}
