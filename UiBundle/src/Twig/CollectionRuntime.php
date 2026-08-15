<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Model\CollectionItem;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;

// Holds CollectionExtension's actual dependencies, split out so Twig only builds this (and the DB query behind CollectionSourceRegistry::addProvider()) the first time a template actually calls collection_render_items() - not eagerly for every request that merely builds the Twig environment (see CollectionExtension)
class CollectionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private readonly CollectionSourceRegistry $sourceRegistry,
        private readonly BlockExtension $blockExtension,
        private readonly RequestStack $requestStack,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Renders each item as a never-persisted "collection_item" Block - literally the same render_block() pipeline as a real, editor-placed block, just fed with data built from the source's own CollectionItem instead of a stored Block::$data; @return string[] one rendered HTML fragment per item
    public function renderItems(string $source, ?int $limit, ?string $detailPage, ?string $variant = null, ?string $order = null): array
    {
        $rendered = [];

        foreach ($this->pick($source, $limit, $order) as $item) {
            $rendered[] = $this->renderItem($source, $item, $detailPage, $variant);
        }

        return $rendered;
    }

    // A random order has to see the whole source before it cuts: asking it for three items straight away would shuffle the same three on every visit, so the limit is applied after the draw and not by the source. Its block is rendered live for the same reason - see CollectionBlockCacheTagProvider; @return CollectionItem[]
    private function pick(string $source, ?int $limit, ?string $order): array
    {
        if ('random' !== $order) {
            return $this->sourceRegistry->items($source, $limit);
        }

        $items = $this->sourceRegistry->items($source, null);
        shuffle($items);

        return null === $limit ? $items : array_slice($items, 0, $limit);
    }

    // The singular of renderItems(): one item of a source, picked by "first"/"last"/its own slug, for the "collection_entry" block - an album on a home page, a member put forward. Empty string when nothing answers (unknown source, empty source, slug that matches none), which the block renders as nothing at all rather than as a hole in the page
    public function renderEntry(string $source, ?string $pick, ?string $slug, ?string $detailPage = null, ?string $variant = null): string
    {
        // "first" only ever needs the head of the source, where the two others have to see the whole of it
        $items = $this->sourceRegistry->items($source, 'first' === $pick ? 1 : null);
        if ([] === $items) {
            return '';
        }

        $item = match ($pick) {
            'slug' => array_find($items, static fn (CollectionItem $candidate): bool => $candidate->slug === $slug),
            'last' => $items[array_key_last($items)],
            default => $items[array_key_first($items)],
        };

        return null === $item ? '' : $this->renderItem($source, $item, $detailPage, $variant);
    }

    // Either the source's own template - a card of its own that this bundle's never draws, see CollectionSourceProviderInterface - or the "collection_item" Block every source has always been rendered by
    private function renderItem(string $source, CollectionItem $item, ?string $detailPage, ?string $variant): string
    {
        $detailUrl = $this->buildDetailUrl($detailPage, $item->slug);

        // The source's own extra data first, so a key it hands back can never take the place of one this method computes - a "detailUrl" or a "variant" of its own would otherwise unlink the item or send it to another template
        $data = [
            ...$item->data,
            'title' => $item->title,
            'content' => $item->description,
            'url' => $item->url,
            'imageUrl' => $item->imageUrl,
            'buttonLabel' => $item->buttonLabel,
            'buttonIcon' => $item->buttonIcon,
            'detailUrl' => $detailUrl,
            'variant' => $variant,
        ];

        $template = $this->sourceRegistry->itemTemplate($source);
        if (null !== $template) {
            // Rendered live, the caching being the named template's own business: it draws an entity this bundle knows nothing of, and is the only side that can say what invalidates it
            return $this->twig->render($template, $data);
        }

        $cacheTags = $this->sourceRegistry->cacheTags($source);

        return $this->blockExtension->renderBlock(
            new Block()->setKind('collection_item')->setData($data),
            $this->itemCacheKey($source, $item->slug, $variant, $detailUrl, $cacheTags),
            $cacheTags
        );
    }

    // The item's own identity rather than the block's, which is the whole point: one character's card is a single entry, hit by the "collection" block listing them on one page and by the one listing them on another. What the key holds is everything the html varies with - the source and the item's slug (the item's own data, invalidated by $cacheTags when it changes), the variant that picks its markup, and the detail url, the one thing in it that the page holding the block decides.
    // Null - i.e. render live, no entry - when the source declared no tag to invalidate on, or when the item has no slug to be named by; @param string[] $cacheTags
    private function itemCacheKey(string $source, ?string $slug, ?string $variant, ?string $detailUrl, array $cacheTags): ?string
    {
        if ([] === $cacheTags || null === $slug) {
            return null;
        }

        // Hashed rather than assembled: a source key, a slug or a detail url all hold characters a cache key reserves ("/", ":", "@"), and the pool refuses the whole entry over one of them
        return 'collection_item_' . hash('xxh128', implode("\0", [$source, $slug, (string) $variant, (string) $detailUrl]));
    }

    // Only when the "collection" block author configured a detailPage AND this item's source hands back a slug: the item's title then links to /pages/{currentPage}/{itemSlug}, resolved back to the source's own "detail" callable by PageController::resolveCollectionDetail(). Tolerant on purpose, like the rest of this feature (see CollectionSourceRegistry::detail()) - anything missing (no detailPage, no slug, no current "page" route parameter) just yields no link, same as a source with no detail page at all. Reuses "page_preview" instead of "page_display" when the parent page itself is being previewed, so an editor can follow a detail link before the parent (or its detailPage) is published, without landing on a 404.
    private function buildDetailUrl(?string $detailPage, ?string $itemSlug): ?string
    {
        if (null === $detailPage || null === $itemSlug) {
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $currentPage = $request?->attributes->get('page');
        if (null === $currentPage) {
            return null;
        }

        $route = 'page_preview' === $request->attributes->get('_route') ? 'page_preview' : 'page_display';

        return $this->urlGenerator->generate($route, ['page' => rtrim($currentPage, '/') . '/' . $itemSlug]);
    }
}
