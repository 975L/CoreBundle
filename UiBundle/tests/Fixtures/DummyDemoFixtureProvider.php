<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Fixtures;

use c975L\UiBundle\Contract\DemoFixtureProviderInterface;

// A stand-in for the demo dataset a satellite bundle contributes - this bundle ships none of its own, owning no entity a demo site is browsed for
class DummyDemoFixtureProvider implements DemoFixtureProviderInterface
{
    public function getDemoFixtures(): iterable
    {
        return [];
    }
}
