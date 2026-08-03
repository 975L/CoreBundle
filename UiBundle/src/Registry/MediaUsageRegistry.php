<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\MediaUsageProviderInterface;

class MediaUsageRegistry
{
    /** @var MediaUsageProviderInterface[] */
    private array $providers = [];

    public function addProvider(MediaUsageProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Merges usages contributed by every provider for the given Media rows
    public function getUsages(array $medias): array
    {
        $usages = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getUsages($medias) as $mediaId => $links) {
                $usages[$mediaId] = array_merge($usages[$mediaId] ?? [], $links);
            }
        }

        return $usages;
    }
}
