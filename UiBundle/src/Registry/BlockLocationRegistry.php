<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BlockLocationProviderInterface;

class BlockLocationRegistry
{
    /** @var BlockLocationProviderInterface[] */
    private array $providers = [];

    public function addProvider(BlockLocationProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Merges the locations contributed by every provider for the given Block rows - each block sits in at most one owner
    public function getLocations(array $blocks): array
    {
        $locations = [];
        foreach ($this->providers as $provider) {
            $locations += $provider->getLocations($blocks);
        }

        return $locations;
    }
}
