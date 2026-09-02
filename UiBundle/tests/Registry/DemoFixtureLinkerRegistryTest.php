<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\DemoFixtureLinkerInterface;
use c975L\UiBundle\Registry\DemoFixtureLinkerRegistry;
use PHPUnit\Framework\TestCase;

class DemoFixtureLinkerRegistryTest extends TestCase
{
    public function testItStartsEmpty(): void
    {
        $this->assertSame([], new DemoFixtureLinkerRegistry()->all());
    }

    // The order they were registered in is the order they are read in, a linker being free to depend on what an earlier one wrote
    public function testItKeepsTheLinkersInTheOrderTheyWereAdded(): void
    {
        $registry = new DemoFixtureLinkerRegistry();
        $first = $this->createStub(DemoFixtureLinkerInterface::class);
        $second = $this->createStub(DemoFixtureLinkerInterface::class);

        $registry->addProvider($first);
        $registry->addProvider($second);

        $this->assertSame([$first, $second], $registry->all());
    }
}
