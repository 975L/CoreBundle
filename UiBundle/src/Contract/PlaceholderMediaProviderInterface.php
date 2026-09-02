<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// Implement to serve the placeholder media a block showcase stands in with (see PlaceholderMediaRegistry), so those demo files can live on the site actually hosting the showcase instead of being shipped by - and downloaded with - UiBundle itself.
interface PlaceholderMediaProviderInterface
{
    /**
     * Web paths from the site root, no leading "/" - the same shape Media::$filename holds. A key left out, or left empty, simply means that media is never attached and the showcase renders the kind without one, so a provider may declare only the kinds of media it actually carries.
     *
     * "images" is the pool a showcase draws from when anything will do. "keyed_images" is the other half: the pictures of one named thing, in the order they are to be attached - "shop/table-basse-chene" being that product's own photographs, which no rotation through a generic pool can stand in for. Keys are namespaced by the bundle the slug belongs to, two bundles being free to name a row alike.
     *
     * "font" is the odd one out: not a picture a showcase stands in with, but the font file the demo dataset imports so the screen listing a site's own fonts is not empty (see UiDemoFixtureProvider). Its name is the one a bulk import reads the family and the weight off, so it is left as the foundry wrote it.
     *
     * @return array{images?: list<string>, keyed_images?: array<string, list<string>>, video?: string, video_embed?: string, audio?: string, document?: string, font?: string}
     */
    public function getPlaceholderMedia(): array;
}
