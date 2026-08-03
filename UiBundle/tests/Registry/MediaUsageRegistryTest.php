<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Registry\MediaUsageRegistry;
use PHPUnit\Framework\TestCase;

class MediaUsageRegistryTest extends TestCase
{
    public function testGetUsagesReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new MediaUsageRegistry();

        $this->assertSame([], $registry->getUsages([]));
    }

    public function testGetUsagesReturnsSingleProviderResult(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([1 => [['label' => 'a', 'url' => null]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $this->assertSame([1 => [['label' => 'a', 'url' => null]]], $registry->getUsages([]));
    }

    // Usages contributed by different providers for the same media id are merged, not overwritten
    public function testGetUsagesMergesEntriesFromMultipleProvidersForSameMediaId(): void
    {
        $providerA = $this->createStub(MediaUsageProviderInterface::class);
        $providerA->method('getUsages')->willReturn([1 => [['label' => 'from-a', 'url' => null]]]);

        $providerB = $this->createStub(MediaUsageProviderInterface::class);
        $providerB->method('getUsages')->willReturn([1 => [['label' => 'from-b', 'url' => null]]]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame(
            [1 => [['label' => 'from-a', 'url' => null], ['label' => 'from-b', 'url' => null]]],
            $registry->getUsages([])
        );
    }

    public function testGetUsagesKeepsEntriesForDifferentMediaIdsSeparate(): void
    {
        $provider = $this->createStub(MediaUsageProviderInterface::class);
        $provider->method('getUsages')->willReturn([
            1 => [['label' => 'a', 'url' => null]],
            2 => [['label' => 'b', 'url' => null]],
        ]);

        $registry = new MediaUsageRegistry();
        $registry->addProvider($provider);

        $usages = $registry->getUsages([]);
        $this->assertArrayHasKey(1, $usages);
        $this->assertArrayHasKey(2, $usages);
    }
}
