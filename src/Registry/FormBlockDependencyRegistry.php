<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\FormBlockDependencyProviderInterface;

class FormBlockDependencyRegistry
{
    /** @var FormBlockDependencyProviderInterface[] */
    private array $providers = [];

    // Called once per provider by FormBlockDependencyProviderPass
    public function addProvider(FormBlockDependencyProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    // Every provider is asked, not just the first: a "register" Block's Form belongs to ConfigBundle and a "contact" one to SiteBundle, and an import can carry both
    public function ensureDependenciesExist(array $blockData): void
    {
        foreach ($this->providers as $provider) {
            $provider->ensureFormBlockDependenciesExist($blockData);
        }
    }
}
