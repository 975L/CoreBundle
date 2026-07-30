<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BundleStylesheetManagementProviderInterface;
use c975L\UiBundle\Registry\StylesheetManagementRegistry;
use PHPUnit\Framework\TestCase;

class StylesheetManagementRegistryTest extends TestCase
{
    private function createProvider(array $stylesheets): BundleStylesheetManagementProviderInterface
    {
        $provider = $this->createStub(BundleStylesheetManagementProviderInterface::class);
        $provider->method('getManagementStylesheets')->willReturn($stylesheets);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new StylesheetManagementRegistry();

        $this->assertSame([], $registry->all());
    }

    public function testAllMergesStylesheetsFromEveryProvider(): void
    {
        $registry = new StylesheetManagementRegistry();
        $registry->addProvider($this->createProvider(['a.css']));
        $registry->addProvider($this->createProvider(['b.css']));

        $this->assertSame(['a.css', 'b.css'], $registry->all());
    }

    // A stylesheet contributed by two different providers must appear only once, in declaration order
    public function testAllDeduplicatesStylesheetsAcrossProviders(): void
    {
        $registry = new StylesheetManagementRegistry();
        $registry->addProvider($this->createProvider(['a.css', 'b.css']));
        $registry->addProvider($this->createProvider(['b.css', 'c.css']));

        $this->assertSame(['a.css', 'b.css', 'c.css'], $registry->all());
    }
}
