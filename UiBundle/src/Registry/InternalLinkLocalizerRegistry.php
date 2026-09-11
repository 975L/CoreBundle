<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\InternalLinkLocalizerInterface;

// Every bundle owning urls of its own gets to rewrite the ones it recognises, one after the other: SiteBundle its pages, and whichever bundle turns multilingual next its own screens. A site with none registered - one declaring a single language, above all - leaves every link exactly as it was stored
class InternalLinkLocalizerRegistry
{
    /** @var InternalLinkLocalizerInterface[] */
    private array $providers = [];

    // Called once per provider by InternalLinkLocalizerPass
    public function addProvider(InternalLinkLocalizerInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Chained rather than first-wins: a rich text holds links of several bundles at once, and each one only rewrites its own
    public function localize(string $value): string
    {
        foreach ($this->providers as $provider) {
            $value = $provider->localize($value);
        }

        return $value;
    }
}
