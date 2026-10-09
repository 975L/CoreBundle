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
use c975L\UiBundle\Service\SharedBlockEditUrl;

class BlockEditUrlRegistry
{
    /** @var BlockEditUrlProviderInterface[] */
    private array $providers = [];

    // Optional so a registry built by hand keeps working: without it a pointer's button opens its page like any other block
    public function __construct(private readonly ?SharedBlockEditUrl $sharedBlockEditUrl = null)
    {
    }

    public function addProvider(BlockEditUrlProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Merges the edit URLs contributed by every provider for the given Block rows - each block is owned by at most one provider
    // A provider failing to build its URLs is skipped rather than left to throw: EasyAdmin resolves the dashboard these URLs are mounted under through a cache map written only when the route collection is regenerated (see AdminRouteGenerator::saveAdminRoutesInCache()), so that pool being emptied while the compiled routes stay fresh makes every generateUrl() call from a public page throw, and it stays that way until the routes are regenerated. These are the editor-only hover buttons of a public page - losing them beats 500ing that page for the only people able to fix it
    public function getEditUrls(array $blocks): array
    {
        // A "shared_block" pointer is edited on its shared block's screen, whichever owner carries it - laid first, so no owner's url replaces it
        try {
            $urls = $this->sharedBlockEditUrl?->getEditUrls($blocks) ?? [];
        } catch (\Throwable) {
            $urls = [];
        }

        foreach ($this->providers as $provider) {
            try {
                $urls += $provider->getEditUrls($blocks);
            } catch (\Throwable) {
                continue;
            }
        }

        return $urls;
    }
}
