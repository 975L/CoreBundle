<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BundleWhatsNewProviderInterface;
use c975L\UiBundle\Registry\WhatsNewRegistry;
use PHPUnit\Framework\TestCase;

class WhatsNewRegistryTest extends TestCase
{
    private function createProvider(array $entries): BundleWhatsNewProviderInterface
    {
        $provider = $this->createStub(BundleWhatsNewProviderInterface::class);
        $provider->method('getEntries')->willReturn($entries);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new WhatsNewRegistry();

        $this->assertSame([], $registry->all());
    }

    // Unlike the script/stylesheet registries, entries are simply concatenated - no deduplication
    public function testAllConcatenatesEntriesFromEveryProviderWithoutDeduplication(): void
    {
        $entryA = ['date' => new \DateTimeImmutable('2026-01-01'), 'description' => ['A']];
        $entryB = ['date' => new \DateTimeImmutable('2026-01-01'), 'description' => ['A']];

        $registry = new WhatsNewRegistry();
        $registry->addProvider($this->createProvider([$entryA]));
        $registry->addProvider($this->createProvider([$entryB]));

        $this->assertSame([$entryA, $entryB], $registry->all());
    }

    public function testAllPreservesProviderRegistrationOrder(): void
    {
        $first = ['date' => new \DateTimeImmutable('2026-01-01'), 'description' => ['first']];
        $second = ['date' => new \DateTimeImmutable('2026-02-01'), 'description' => ['second']];

        $registry = new WhatsNewRegistry();
        $registry->addProvider($this->createProvider([$second]));
        $registry->addProvider($this->createProvider([$first]));

        $this->assertSame([$second, $first], $registry->all());
    }
}
