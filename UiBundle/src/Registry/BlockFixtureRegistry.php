<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\BlockFixtureProviderInterface;

class BlockFixtureRegistry
{
    private array $fixtures = [];

    // Called once per tagged provider by BlockFixtureProviderPass
    public function addProvider(BlockFixtureProviderInterface $provider): void
    {
        $this->fixtures = array_merge($this->fixtures, $provider->getFixtures());
    }

    public function has(string $kind): bool
    {
        return isset($this->fixtures[$kind]);
    }

    public function get(string $kind): array
    {
        return $this->fixtures[$kind] ?? [];
    }
}
