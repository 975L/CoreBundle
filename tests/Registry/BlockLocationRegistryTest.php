<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BlockLocationProviderInterface;
use c975L\UiBundle\Registry\BlockLocationRegistry;
use PHPUnit\Framework\TestCase;

class BlockLocationRegistryTest extends TestCase
{
    private function createProvider(array $locations): BlockLocationProviderInterface
    {
        $provider = $this->createStub(BlockLocationProviderInterface::class);
        $provider->method('getLocations')->willReturn($locations);

        return $provider;
    }

    // What an app running Ui with no bundle owning blocks answers: the screens list their blocks unlocated
    public function testGetLocationsReturnsEmptyArrayWhenNoProviders(): void
    {
        $this->assertSame([], (new BlockLocationRegistry())->getLocations([]));
    }

    public function testGetLocationsReturnsSingleProviderResult(): void
    {
        $registry = new BlockLocationRegistry();
        $registry->addProvider($this->createProvider([1 => ['label' => 'Cookies', 'url' => '/pages/cookies', 'published' => true]]));

        $this->assertSame(
            [1 => ['label' => 'Cookies', 'url' => '/pages/cookies', 'published' => true]],
            $registry->getLocations([]),
        );
    }

    // A block sits in at most one owner - the first provider to claim an id wins
    public function testGetLocationsKeepsFirstProviderResultForSameBlockId(): void
    {
        $registry = new BlockLocationRegistry();
        $registry->addProvider($this->createProvider([1 => ['label' => 'From A', 'url' => null, 'published' => false]]));
        $registry->addProvider($this->createProvider([1 => ['label' => 'From B', 'url' => null, 'published' => false]]));

        $this->assertSame('From A', $registry->getLocations([])[1]['label']);
    }

    public function testGetLocationsMergesProvidersOwningDifferentBlocks(): void
    {
        $registry = new BlockLocationRegistry();
        $registry->addProvider($this->createProvider([1 => ['label' => 'A', 'url' => null, 'published' => false]]));
        $registry->addProvider($this->createProvider([2 => ['label' => 'B', 'url' => null, 'published' => false]]));

        $this->assertSame([1, 2], array_keys($registry->getLocations([])));
    }
}
