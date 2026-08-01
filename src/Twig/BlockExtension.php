<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockCacheTagRegistry;
use c975L\UiBundle\Registry\BlockEditUrlRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BlockExtension extends AbstractExtension
{
    public function __construct(
        private BlockRegistry $registry,
        private Environment $twig,
        private TagAwareCacheInterface $cache,
        private RequestStack $requestStack,
        private BlockCacheTagRegistry $cacheTagRegistry,
        private BlockEditUrlRegistry $blockEditUrlRegistry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_block', [$this, 'renderBlock'], ['is_safe' => ['html']]),
            new TwigFunction('block_edit_urls', [$this, 'getBlockEditUrls']),
        ];
    }

    // Resolved once for the whole collection (not per block) to avoid a query per block - see BlockEditUrlRegistry
    public function getBlockEditUrls(iterable $blocks): array
    {
        return $this->blockEditUrlRegistry->getEditUrls(is_array($blocks) ? $blocks : iterator_to_array($blocks));
    }

    public function renderBlock(Block $block): string
    {
        $kind = $block->getKind();

        // A slot added with "+" but saved without a kind, CollectionType letting the entry through - or a kind
        // no longer registered (a satellite bundle removed, a kind dropped): the row outlives its own kind, so it
        // is skipped rather than left to throw "Unknown block" out of the registry and 500 the page holding it
        if (null === $kind || !$this->registry->has($kind)) {
            return '';
        }

        // A never-persisted block (e.g. a block showcase's in-memory fixture previews, see BlockFixtureRegistry) has no id - caching it by id would collapse every such block onto the same "block_render_0_..." key, silently serving one block's rendered HTML for every other one
        if (null === $block->getId() || !$this->registry->isCacheable($kind)) {
            return $this->doRender($block);
        }

        $request = $this->requestStack->getCurrentRequest();

        // Rendered outside any http request (a console command, a messenger worker): the RequestContext then falls back to its "http://localhost" defaults, which "framework.router.default_uri" does not fill in, so anything request-dependent in a template would be frozen into the entry and served to every visitor afterwards - rendered, never cached
        if (null === $request) {
            return $this->doRender($block);
        }

        // Locale is part of the key: some templates (e.g. legal_model) render different content per app.request.locale, not just per Block::$data
        $locale = $request->getLocale();

        return $this->cache->get(
            sprintf('block_render_%d_%s', $block->getId(), $locale),
            function (ItemInterface $item) use ($block): string {
                $item->expiresAfter(null);
                $item->tag([
                    'block_' . $block->getId(),
                    BlockCacheInvalidator::CACHE_TAG_ALL,
                    ...$this->cacheTagRegistry->getExtraTags($block),
                ]);

                return $this->doRender($block);
            }
        );
    }

    private function doRender(Block $block): string
    {
        $data = $block->getData();

        return $this->twig->render(
            $this->registry->getTemplate($block->getKind()),
            ['block' => $block, 'anchor_id' => $this->buildAnchorId($data['anchor'] ?? null, $block->getId())] + $data
        );
    }

    // Computed once here instead of every "Page sections" adapter template repeating its own "{{ anchor ~ '-' ~ block.id }}" - the trailing block id keeps two blocks of the same kind (or the same title/anchor reused elsewhere) on the same page from colliding on the same HTML id
    private function buildAnchorId(?string $anchor, ?int $blockId): string
    {
        return $anchor ? $anchor . '-' . $blockId : '';
    }
}
