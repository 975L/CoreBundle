<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Block;

// Every in-page anchor a set of Blocks declares, as "fragment => label" - single source shared by SiteBundle's MenuLinkType (listing the anchors a menu link can target) and MenuExtension (labelling an already saved "page:ID#fragment" target), so both always agree on what a block's rendered HTML id actually is. Nested container slots are walked too: the sections actually visible on a page are often those, not the container holding them
class BlockAnchorCollector
{
    // Kinds whose rendered id prefixes the slug instead of using it as-is (see Article.html.twig's id="article-{slug}")
    private const SLUG_PREFIXES = ['article' => 'article-'];

    // @param iterable<Block> $blocks
    // @return array<string, string>
    public function collect(iterable $blocks): array
    {
        $anchors = [];
        foreach ($blocks as $block) {
            $anchor = $this->anchor($block);
            if (null !== $anchor) {
                $anchors[$anchor['fragment']] = $anchor['label'];
            }

            $anchors += $this->collect($block->getSlots());
        }

        return $anchors;
    }

    // The fragment (HTML id) a block is reachable at, plus the label naming it - null when it declares no anchor at all
    // @return array{fragment: string, label: string}|null
    private function anchor(Block $block): ?array
    {
        $data = $block->getData();
        $anchor = trim((string) ($data['anchor'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));

        if ('' === $anchor && '' === $slug) {
            return null;
        }

        // "anchor" (see HasAnchorFieldTrait) is rendered as "<anchor>-<blockId>" by BlockExtension::buildAnchorId(), the trailing id keeping two blocks sharing the same anchor apart; a "slug" (auto-derived from the title, e.g. "text_section") is rendered as-is
        return [
            'fragment' => '' !== $anchor
                ? $anchor . '-' . $block->getId()
                : (self::SLUG_PREFIXES[$block->getKind()] ?? '') . $slug,
            'label' => $this->label($data, '' !== $anchor ? $anchor : $slug),
        ];
    }

    // The block's own title, falling back to its eyebrow (the heading of a section typed with no title, see Text/Section.html.twig) then to the raw anchor - markup is stripped because some kinds (hero, cta_band) use a TrixEditorType title, whose inline tags must not leak into a plain-text select option or a menu label
    private function label(array $data, string $fallback): string
    {
        foreach (['title', 'eyebrow'] as $key) {
            // Each tag becomes a space, then entities are decoded back to the character they stand for - a Trix title stores "&" as "&amp;" and a non-breaking space as "&nbsp;", both of which would otherwise read literally in a select option. Decoded after the strip, never before, else a "&lt;b&gt;" the editor typed as text would come back as a tag
            $plain = html_entity_decode((string) preg_replace('/<[^>]*>/', ' ', (string) ($data[$key] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Runs of whitespace collapse, U+00A0 included since "\s" doesn't cover it: stripping alone would weld the two lines of a "Marre<br>des radars" title into "Marredes radars"
            $label = trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $plain));
            if ('' !== $label) {
                return $label;
            }
        }

        return $fallback;
    }
}
