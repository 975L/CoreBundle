<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\DemoFixtureProviderInterface;
use c975L\UiBundle\Registry\DemoFixtureRegistry;
use PHPUnit\Framework\TestCase;

class DemoFixtureRegistryTest extends TestCase
{
    private function createProvider(): DemoFixtureProviderInterface
    {
        return $this->createStub(DemoFixtureProviderInterface::class);
    }

    public function testRegistryStartsEmpty(): void
    {
        $this->assertSame([], new DemoFixtureRegistry()->all());
    }

    // Registration order is the loading order, and its reverse is the emptying order, so the registry hands the providers back exactly as they came
    public function testProvidersAreKeptInRegistrationOrder(): void
    {
        $first = $this->createProvider();
        $second = $this->createProvider();

        $registry = new DemoFixtureRegistry();
        $registry->addProvider($first);
        $registry->addProvider($second);

        $this->assertSame([$first, $second], $registry->all());
    }
}
