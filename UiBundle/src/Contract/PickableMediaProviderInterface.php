<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use c975L\UiBundle\Model\PickableMedia;
use Symfony\Contracts\Translation\TranslatableInterface;

// Implement to offer a bundle's own pictures and videos to be picked elsewhere - a gallery's photographs attached to a social post - without the bundle picking them requiring the one owning them (auto-discovered by interface, no tag needed)
interface PickableMediaProviderInterface
{
    // The name the medias are shown under ("Gallery")
    public function getPickableMediaLabel(): TranslatableInterface;

    // The latest medias first, their title or their folder's containing the search when one is given
    /** @return list<PickableMedia> */
    public function findPickableMedia(string $search, int $limit): array;
}
