<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\String\Slugger\SluggerInterface;

// Shared by every "Page sections" block kind's FormType (via HasAnchorFieldTrait) to derive the short, stable slug stored as the block's "anchor" - an editor-typed value always wins over the title fallback, letting them keep a long section title but a short readable in-page anchor (e.g. "Des services taillés autour de votre projet" -> "services"). The trailing "-{block.id}" that makes the final HTML id/URL fragment unique when the same kind appears twice on a page is NOT added here: it's appended at render time (see blocks/*.html.twig), since a block has no id yet while this runs (FormEvents::SUBMIT, before the entity is persisted).
class BlockAnchorSlugger
{
    public function __construct(private readonly SluggerInterface $slugger)
    {
    }

    public function slugify(?string $anchor, ?string $fallback): ?string
    {
        // strip_tags: the title fallback may come from a TrixEditorType field (e.g. HeroType's rich-text title), whose inline markup must not leak into the slug as stray words
        $source = '' !== trim((string) $anchor) ? $anchor : strip_tags((string) $fallback);

        return '' !== trim($source) ? strtolower($this->slugger->slug($source)->toString()) : null;
    }
}
