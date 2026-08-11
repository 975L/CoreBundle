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
use c975L\UiBundle\Service\CspNonceProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BlockExtension extends AbstractExtension
{
    // Written by the block templates, replaced by the real nonce at serve time (see applyNonce)
    private const string NONCE_MARKER = 'data-ui-nonce';

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly Environment $twig,
        private readonly TagAwareCacheInterface $cache,
        private readonly RequestStack $requestStack,
        private readonly BlockCacheTagRegistry $cacheTagRegistry,
        private readonly BlockEditUrlRegistry $blockEditUrlRegistry,
        private readonly CspNonceProvider $cspNonceProvider,
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('render_block', $this->renderBlock(...), ['is_safe' => ['html']]),
            new TwigFunction('block_edit_urls', $this->getBlockEditUrls(...)),
        ];
    }

    // Resolved once for the whole collection (not per block) to avoid a query per block - see BlockEditUrlRegistry
    public function getBlockEditUrls(iterable $blocks): array
    {
        return $this->blockEditUrlRegistry->getEditUrls(is_array($blocks) ? $blocks : iterator_to_array($blocks));
    }

    // Every path goes through applyNonce, the early returns below included: a block rendered without being cached (no id, a kind the registry declares uncacheable, a render outside any request) ships the same marker and would otherwise leak it raw into the page
    public function renderBlock(Block $block): string
    {
        return $this->applyNonce($this->wrapInAnimation($block, $this->renderHtml($block)));
    }

    // The entrance effect belongs to the block, not to the place it happens to be rendered from - hence here rather than in components/Blocks/Block.html.twig, which only ever wraps the blocks of a page's own run. A slot of a container kind (a card in a "section_cards", a block in a "flex_column", a video in a "video_grid") goes through render_block() straight, so its animation was stored, offered on the edit screen, and read by nothing at all.
    // Outside the cache renderHtml() writes to: the wrapper is two attributes computed from the block itself, where the cache entry is the kind's whole template
    private function wrapInAnimation(Block $block, string $html): string
    {
        $animation = (string) $block->getAnimation();
        if ('' === $animation) {
            return $html;
        }

        // "hidden" is added by animate-scroll.js on connect, so nothing is ever hidden if that script fails to load. The wrapper is "display: contents" (sass/_animations-media.scss), so it never becomes the flex/grid item the layout around it addresses
        return sprintf(
            '<div class="block-animation scroll" data-animation="%s">%s</div>',
            htmlspecialchars($animation, \ENT_QUOTES),
            $html
        );
    }

    private function renderHtml(Block $block): string
    {
        $kind = $block->getKind();

        // A slot added with "+" but saved without a kind, CollectionType letting the entry through - or a kind no longer registered (a satellite bundle removed, a kind dropped): the row outlives its own kind, so it is skipped rather than left to throw "Unknown block" out of the registry and 500 the page holding it
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

    // A block that has to ship a <style> of its own (per-instance values a class cannot carry - see components/Banner/Title.html.twig) writes a bare "data-ui-nonce" marker rather than the nonce itself: the rendered html above is cached verbatim, so a real nonce would freeze into the entry and match nothing on every later request, which is exactly what the note on $request warns about.
    // Substituting it here happens on the cache hit as well as on the miss, so the nonce is always the one of the response being built
    private function applyNonce(string $html): string
    {
        if (!str_contains($html, self::NONCE_MARKER)) {
            return $html;
        }

        $nonce = $this->cspNonceProvider->styleNonce();

        // Only a marker carried by a <style> opening tag is substituted: the plain string replacement this used to be also reached the rich text a block renders raw, so a "<style data-ui-nonce>" pasted into a Trix field came back out holding a nonce the policy trusts - an editor writing his own authorized style through the block
        // A site with no csp section has no nonce to write, and an empty one would be a nonce="" no policy ever matches - the marker goes away with the space that separates it from the attribute before it
        return (string) preg_replace_callback(
            '/(<style(?:\s[^>]*?)?)\s' . self::NONCE_MARKER . '(?=[\s>])/',
            static fn (array $matches): string => '' === $nonce
                ? $matches[1]
                : $matches[1] . sprintf(' nonce="%s"', htmlspecialchars($nonce, \ENT_QUOTES)),
            $html
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
