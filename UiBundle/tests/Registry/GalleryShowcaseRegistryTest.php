<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\Registry\GalleryShowcaseRegistry;
use PHPUnit\Framework\TestCase;

class GalleryShowcaseRegistryTest extends TestCase
{
    private function createProvider(array $showcases): GalleryShowcaseProviderInterface
    {
        $provider = $this->createStub(GalleryShowcaseProviderInterface::class);
        $provider->method('getShowcases')->willReturn($showcases);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new GalleryShowcaseRegistry();

        $this->assertSame([], $registry->all());
    }

    public function testAllMergesShowcasesFromEveryProvider(): void
    {
        $registry = new GalleryShowcaseRegistry();
        $registry->addProvider($this->createProvider(['Réseaux sociaux' => ['variants' => ['Minimal' => '<ul></ul>']]]));
        $registry->addProvider($this->createProvider(['Boutons de partage' => ['variants' => ['Distinct' => '<div></div>']]]));

        $all = $registry->all();
        $this->assertCount(2, $all);
        $this->assertArrayHasKey('Réseaux sociaux', $all);
        $this->assertArrayHasKey('Boutons de partage', $all);
    }

    // A later provider registering the same label overrides the earlier one, same as array_merge()
    public function testLaterProviderOverridesEarlierOneForTheSameLabel(): void
    {
        $registry = new GalleryShowcaseRegistry();
        $registry->addProvider($this->createProvider(['Réseaux sociaux' => ['variants' => ['Minimal' => '<ul></ul>']]]));
        $registry->addProvider($this->createProvider(['Réseaux sociaux' => ['variants' => ['Coloré' => '<ul class="c"></ul>']]]));

        $this->assertSame(['variants' => ['Coloré' => '<ul class="c"></ul>']], $registry->all()['Réseaux sociaux']);
    }
}
