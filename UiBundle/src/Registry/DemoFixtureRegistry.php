<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\DemoFixtureProviderInterface;

// Holds the providers themselves rather than their merged fixtures, the way BlockFixtureRegistry does - a demo application reads them in the order they were registered, and loads what each one yields
class DemoFixtureRegistry
{
    /** @var list<DemoFixtureProviderInterface> */
    private array $providers = [];

    // Called once per provider by DemoFixtureProviderPass
    public function addProvider(DemoFixtureProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /** @return list<DemoFixtureProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }
}
