<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\SameAsProviderInterface;

class SameAsRegistry
{
    /**
     * @var SameAsProviderInterface[]
     */
    private array $providers = [];

    // Called once per provider by SameAsProviderPass
    public function addProvider(SameAsProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Read at render time rather than merged here: a provider reads the profiles from the database, and a registry filled at compile time would freeze whatever they were when the container was built
    /** @return string[] */
    public function all(): array
    {
        $urls = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->getSameAs() as $url) {
                $url = trim((string) $url);

                // De-duplicated across providers: two bundles naming the same profile would otherwise publish it twice, which reads as two entities
                if ('' !== $url && !in_array($url, $urls, true)) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }
}
