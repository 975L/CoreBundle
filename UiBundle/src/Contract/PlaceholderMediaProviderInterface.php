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
     * @return array{images?: list<string>, video?: string, video_embed?: string, audio?: string, document?: string}
     */
    public function getPlaceholderMedia(): array;
}
