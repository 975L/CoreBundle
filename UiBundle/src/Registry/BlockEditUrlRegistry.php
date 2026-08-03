<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;

class BlockEditUrlRegistry
{
    /** @var BlockEditUrlProviderInterface[] */
    private array $providers = [];

    public function addProvider(BlockEditUrlProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Merges the edit URLs contributed by every provider for the given Block rows - each block is owned by at most one provider
    public function getEditUrls(array $blocks): array
    {
        $urls = [];
        foreach ($this->providers as $provider) {
            $urls += $provider->getEditUrls($blocks);
        }

        return $urls;
    }
}
