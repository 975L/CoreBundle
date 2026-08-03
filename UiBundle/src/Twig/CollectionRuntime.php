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
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\RuntimeExtensionInterface;

// Holds CollectionExtension's actual dependencies, split out so Twig only builds this (and the DB query behind CollectionSourceRegistry::addProvider()) the first time a template actually calls collection_render_items() - not eagerly for every request that merely builds the Twig environment (see CollectionExtension)
class CollectionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private CollectionSourceRegistry $sourceRegistry,
        private BlockExtension $blockExtension,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Renders each item as a never-persisted "collection_item" Block - literally the same render_block() pipeline as a real, editor-placed block, just fed with data built from the source's own CollectionItem instead of a stored Block::$data; @return string[] one rendered HTML fragment per item
    public function renderItems(string $source, ?int $limit, ?string $detailPage, ?string $variant = null): array
    {
        $rendered = [];
        foreach ($this->sourceRegistry->items($source, $limit) as $item) {
            $block = (new Block())->setKind('collection_item')->setData([
                'title' => $item->title,
                'content' => $item->description,
                'url' => $item->url,
                'imageUrl' => $item->imageUrl,
                'buttonLabel' => $item->buttonLabel,
                'buttonIcon' => $item->buttonIcon,
                'detailUrl' => $this->buildDetailUrl($detailPage, $item->slug),
                'variant' => $variant,
            ]);
            $rendered[] = $this->blockExtension->renderBlock($block);
        }

        return $rendered;
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
