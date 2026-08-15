<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\BlockEditUrlRegistry;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockCacheInvalidator;
use c975L\UiBundle\Service\BlockCacheTagResolver;
use c975L\UiBundle\Service\BlockRenderContext;
use c975L\UiBundle\Service\CspNonceProvider;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

class BlockExtension
{
    // Written by the block templates, replaced by the real nonce at serve time (see applyNonce)
    private const string NONCE_MARKER = 'data-ui-nonce';

    // How many render_block() calls are currently open, a container's slots being rendered from inside its own template (see renderBlock)
    private int $renderDepth = 0;

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly Environment $twig,
        private readonly TagAwareCacheInterface $cache,
        private readonly RequestStack $requestStack,
        private readonly BlockCacheTagResolver $cacheTagResolver,
        private readonly BlockEditUrlRegistry $blockEditUrlRegistry,
        private readonly CspNonceProvider $cspNonceProvider,
        private readonly BlockRenderContext $renderContext,
    ) {
    }

    // Resolved once for the whole collection (not per block) to avoid a query per block - see BlockEditUrlRegistry
    #[AsTwigFunction('block_edit_urls')]
    public function getBlockEditUrls(iterable $blocks): array
    {
        return $this->blockEditUrlRegistry->getEditUrls(is_array($blocks) ? $blocks : iterator_to_array($blocks));
    }

    // Every path goes through applyNonce, the early returns below included: a block rendered without being cached (no id, a kind the registry declares uncacheable, a render outside any request) ships the same marker and would otherwise leak it raw into the page
    // $cacheKey/$cacheTags are for the never-persisted blocks a caller builds itself and can identify better than an id could (see CollectionRuntime, whose items are transient by design but named by their source's own slug) - left out by every Twig caller, "render_block" only ever taking the block
    #[AsTwigFunction('render_block', isSafe: ['html'])]
    public function renderBlock(Block $block, ?string $cacheKey = null, array $cacheTags = []): string
    {
        ++$this->renderDepth;

        try {
            $html = $this->wrapInAnimation($block, $this->wrapInCssClasses($block, $this->renderHtml($block, $cacheKey, $cacheTags)));
        } finally {
            --$this->renderDepth;
        }

        // A slot's html is stored verbatim in its container's cache entry, so the marker stays put until the outermost render - the only one that happens on every request, and the only one whose nonce is the one of the response being built
        return 0 === $this->renderDepth ? $this->applyNonce($html) : $html;
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

    // The site's own classes (see HasCssClassesFieldTrait), on a wrapper rather than on the kind's own markup: every kind then supports the field the moment its FormType offers it, with nothing to thread through its template - and a class of the site's never lands on an element the bundle styles itself. Inside the animation wrapper, that one being "display: contents" and this one having to be the box the layout addresses.
    // Outside the cache renderHtml() writes to, same as wrapInAnimation: the wrapper is one attribute computed from the block itself
    private function wrapInCssClasses(Block $block, string $html): string
    {
        $classes = $this->cssClasses($block);
        if ('' === $classes) {
            return $html;
        }

        return sprintf('<div class="%s">%s</div>', htmlspecialchars($classes, \ENT_QUOTES), $html);
    }

    // Filtered here rather than on submit: block data also reaches the page through an import (see BlockDataImporter, which stores the "data" array whole), and the render is the one gate both paths go through. Anything that is not a plain class name is dropped rather than escaped into the attribute - a value like 'a" onclick="x' would otherwise sit there, harmless but for good only by the escaping above
    private function cssClasses(Block $block): string
    {
        $stored = $block->getData()['cssClasses'] ?? null;
        if (!is_string($stored)) {
            return '';
        }

        $names = array_filter(
            preg_split('/\s+/', trim($stored), -1, \PREG_SPLIT_NO_EMPTY) ?: [],
            static fn (string $name): bool => 1 === preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)
        );

        return implode(' ', array_unique($names));
    }

    private function renderHtml(Block $block, ?string $cacheKey, array $cacheTags): string
    {
        $kind = $block->getKind();

        // A slot added with "+" but saved without a kind, CollectionType letting the entry through - or a kind no longer registered (a satellite bundle removed, a kind dropped): the row outlives its own kind, so it is skipped rather than left to throw "Unknown block" out of the registry and 500 the page holding it
        if (null === $kind || !$this->registry->has($kind)) {
            return '';
        }

        // A never-persisted block (e.g. a block showcase's in-memory fixture previews, see BlockFixtureRegistry) has no id - caching it by id would collapse every such block onto the same "block_render_0_..." key, silently serving one block's rendered HTML for every other one. Only a caller handing its own key gets such a block cached, having named it itself
        $key = null !== $block->getId() ? 'block_render_' . $block->getId() : $cacheKey;
        if (null === $key) {
            return $this->doRender($block);
        }

        // An editor's preview: fresh output, and none of it written where the public site would read it - see BlockRenderContext
        if ($this->renderContext->isCacheDisabled()) {
            return $this->doRender($block);
        }

        $request = $this->requestStack->getCurrentRequest();

        // Rendered outside any http request (a console command, a messenger worker): the RequestContext then falls back to its "http://localhost" defaults, which "framework.router.default_uri" does not fill in, so anything request-dependent in a template would be frozen into the entry and served to every visitor afterwards - rendered, never cached
        if (null === $request) {
            return $this->doRender($block);
        }

        // Locale is part of the key: some templates (e.g. legal_model) render different content per app.request.locale, not just per Block::$data
        $locale = $request->getLocale();

        // Resolved inside the callback rather than before the get(): for a container it walks the whole slot subtree, hydrating it from the database, and on a hit that work would be thrown away along with the tags it computed
        return $this->cache->get(
            $key . '_' . $locale,
            function (ItemInterface $item, bool &$save) use ($block, $cacheTags): string {
                // The kind's own "cacheable", plus whatever its instance has to say about it and the tags that go with it - a container's slots included, its html holding theirs (see BlockCacheTagResolver). Null is the veto, and $save is how the contract says "render this one, store nothing"
                $extraTags = $this->cacheTagResolver->resolve($block);
                if (null === $extraTags) {
                    $save = false;

                    return $this->doRender($block);
                }

                $item->expiresAfter(null);

                // No "block_{id}" for a transient block, which has none - what reaches its entry is the tags its caller declared instead
                $own = null !== $block->getId() ? ['block_' . $block->getId()] : [];
                $item->tag([...$own, BlockCacheInvalidator::CACHE_TAG_ALL, ...$extraTags, ...$cacheTags]);

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
