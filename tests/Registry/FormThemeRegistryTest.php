<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\FormThemeProviderInterface;
use c975L\UiBundle\Registry\FormThemeRegistry;
use PHPUnit\Framework\TestCase;

class FormThemeRegistryTest extends TestCase
{
    private function createProvider(array $themes): FormThemeProviderInterface
    {
        $provider = $this->createStub(FormThemeProviderInterface::class);
        $provider->method('getFormThemes')->willReturn($themes);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new FormThemeRegistry();

        $this->assertSame([], $registry->all());
    }

    public function testAllMergesThemesFromEveryProvider(): void
    {
        $registry = new FormThemeRegistry();
        $registry->addProvider($this->createProvider(['@c975LUi/form/block_theme.html.twig']));
        $registry->addProvider($this->createProvider(['@c975LSite/form/page_theme.html.twig']));

        $this->assertSame(
            ['@c975LUi/form/block_theme.html.twig', '@c975LSite/form/page_theme.html.twig'],
            $registry->all()
        );
    }

    // A theme contributed by two different providers must appear only once, in declaration order
    public function testAllDeduplicatesThemesAcrossProviders(): void
    {
        $registry = new FormThemeRegistry();
        $registry->addProvider($this->createProvider(['a.html.twig', 'b.html.twig']));
        $registry->addProvider($this->createProvider(['b.html.twig', 'c.html.twig']));

        $this->assertSame(['a.html.twig', 'b.html.twig', 'c.html.twig'], $registry->all());
    }
}
