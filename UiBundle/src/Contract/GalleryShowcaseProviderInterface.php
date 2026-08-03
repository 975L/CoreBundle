<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

/**
 * Implement to add non-block content to a block showcase (see GalleryShowcaseRegistry), for a component or Twig function whose visual styles are worth showcasing but that isn't a "ui.block" kind.
 */
interface GalleryShowcaseProviderInterface
{
    /**
     * "kind" is the real block kind this stands in for (joins its category, suppresses that kind's own preview card, null when none); "category" overrides it directly and takes precedence; "wide" is currently a no-op, kept for existing/future fixed-width consumers.
     *
     * @return array<string, array{description: string, kind?: ?string, category?: ?string, wide?: ?bool, variants: array<string, string>}> showcase label => showcase, "variants" being variant label => already-rendered HTML
     */
    public function getShowcases(): array;
}
