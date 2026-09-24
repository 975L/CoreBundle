<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\LinkTargetProviderInterface;

// Every page and section a block's link field can point at, merged across the bundles owning them (see LinkTargetType)
class LinkTargetRegistry
{
    /** @var LinkTargetProviderInterface[] */
    private array $providers = [];

    /** @var array<string, string>|null */
    private ?array $targets = null;

    // Called once per provider by LinkTargetProviderPass
    public function addProvider(LinkTargetProviderInterface $provider): void
    {
        $this->providers[] = $provider;
        $this->targets = null;
    }

    // Read once per request and sorted by label: a page holds a dozen link fields, and a provider walks every page of the site to list its sections
    /** @return array<string, string> label => value */
    public function all(): array
    {
        if (null !== $this->targets) {
            return $this->targets;
        }

        $targets = [];
        foreach ($this->providers as $provider) {
            $targets += $provider->linkTargets();
        }

        ksort($targets, SORT_NATURAL | SORT_FLAG_CASE);

        return $this->targets = $targets;
    }
}
