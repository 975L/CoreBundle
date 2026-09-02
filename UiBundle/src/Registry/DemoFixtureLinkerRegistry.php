<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Registry;

use c975L\UiBundle\Contract\DemoFixtureLinkerInterface;

// Holds the linkers themselves rather than their merged fixtures, the way DemoFixtureRegistry does - a demo application reads them once the first pass is flushed, and loads what each one yields
class DemoFixtureLinkerRegistry
{
    /** @var list<DemoFixtureLinkerInterface> */
    private array $linkers = [];

    // Called once per linker by DemoFixtureLinkerPass
    public function addProvider(DemoFixtureLinkerInterface $linker): void
    {
        $this->linkers[] = $linker;
    }

    /** @return list<DemoFixtureLinkerInterface> */
    public function all(): array
    {
        return $this->linkers;
    }
}
