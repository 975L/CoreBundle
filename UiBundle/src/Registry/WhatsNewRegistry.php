<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BundleWhatsNewProviderInterface;

class WhatsNewRegistry
{
    /** @var BundleWhatsNewProviderInterface[] */
    private array $providers = [];

    public function addProvider(BundleWhatsNewProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Returns all entries contributed by UiBundle-hosted providers (e.g. new blocks), unsorted
    public function all(): array
    {
        $entries = [];
        foreach ($this->providers as $provider) {
            $entries = array_merge($entries, $provider->getEntries());
        }

        return $entries;
    }
}
