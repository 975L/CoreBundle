<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;

class GalleryShowcaseRegistry
{
    private array $showcases = [];

    // Called once per tagged provider by GalleryShowcaseProviderPass
    public function addProvider(GalleryShowcaseProviderInterface $provider): void
    {
        $this->showcases = array_merge($this->showcases, $provider->getShowcases());
    }

    public function all(): array
    {
        return $this->showcases;
    }
}
