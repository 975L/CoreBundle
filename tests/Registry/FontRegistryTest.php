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

    public function testGetFontsDelegatesToRegisteredProvider(): void
    {
        $provider = $this->createStub(FontProviderInterface::class);
        $provider->method('getFonts')->willReturn(['Roboto', 'Lato']);

        $registry = new FontRegistry();
        $registry->addProvider($provider);

        $this->assertSame(['Roboto', 'Lato'], $registry->getFonts());
    }

    // Only one app-wide font source is expected - the first registered provider wins
    public function testGetFontsKeepsFirstProviderResultWhenSeveralAreRegistered(): void
    {
        $providerA = $this->createStub(FontProviderInterface::class);
        $providerA->method('getFonts')->willReturn(['Roboto']);

        $providerB = $this->createStub(FontProviderInterface::class);
        $providerB->method('getFonts')->willReturn(['Lato']);

        $registry = new FontRegistry();
        $registry->addProvider($providerA);
        $registry->addProvider($providerB);

        $this->assertSame(['Roboto'], $registry->getFonts());
    }
}
