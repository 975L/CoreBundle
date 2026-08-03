<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\FontProviderInterface;
use c975L\UiBundle\Registry\FontRegistry;
use PHPUnit\Framework\TestCase;

class FontRegistryTest extends TestCase
{
    public function testGetFontsReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new FontRegistry();

        $this->assertSame([], $registry->getFonts());
    }

    // The select needs a stable, alphabetical list, whatever order a provider answers in
    public function testGetFontsSortsWhatItsProviderReturns(): void
    {
        $provider = $this->createStub(FontProviderInterface::class);
        $provider->method('getFonts')->willReturn(['Roboto', 'Lato']);

        $registry = new FontRegistry();
        $registry->addProvider($provider);

        $this->assertSame(['Lato', 'Roboto'], $registry->getFonts());
    }

    // Font families add up - this bundle's own FontService always being registered, a "first one wins" would silently mask an app's provider
    public function testGetFontsMergesEveryProvider(): void
    {
        $providerA = $this->createStub(FontProviderInterface::class);
        $providerA->method('getFonts')->willReturn(['Roboto']);

        $providerB = $this->createStub(FontProviderInterface::class);
        $providerB->method('getFonts')->willReturn(['Lato']);

        $registry = new FontRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame(['Lato', 'Roboto'], $registry->getFonts());
    }

    // The same family declared by two providers (an uploaded font also listed in the app's theme) must appear once
    public function testGetFontsDeduplicatesAcrossProviders(): void
    {
        $providerA = $this->createStub(FontProviderInterface::class);
        $providerA->method('getFonts')->willReturn(['Roboto', 'Lato']);

        $providerB = $this->createStub(FontProviderInterface::class);
        $providerB->method('getFonts')->willReturn(['Lato', 'Inter']);

        $registry = new FontRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame(['Inter', 'Lato', 'Roboto'], $registry->getFonts());
    }
}
