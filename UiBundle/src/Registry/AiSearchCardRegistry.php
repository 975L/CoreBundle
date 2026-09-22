<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\AiSearchCardProviderInterface;

class AiSearchCardRegistry
{
    /**
     * @var AiSearchCardProviderInterface[]
     */
    private array $providers = [];

    // Called once per provider by AiSearchCardProviderPass
    public function addProvider(AiSearchCardProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // The first card a provider gives for each url, in the order the urls came
    /**
     * @param list<string> $urls
     *
     * @return array<string, string>
     */
    public function render(array $urls): array
    {
        $cards = [];
        foreach ($this->providers as $provider) {
            $cards += $provider->renderCards(array_values(array_diff($urls, array_keys($cards))));
        }

        $ordered = [];
        foreach ($urls as $url) {
            if (isset($cards[$url])) {
                $ordered[$url] = $cards[$url];
            }
        }

        return $ordered;
    }
}
