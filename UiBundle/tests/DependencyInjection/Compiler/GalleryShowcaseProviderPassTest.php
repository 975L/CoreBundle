<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\GalleryShowcaseProviderPass;
use c975L\UiBundle\Registry\GalleryShowcaseRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class GalleryShowcaseProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        (new GalleryShowcaseProviderPass())->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements GalleryShowcaseProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryShowcaseProviderImplementation(): void
    {
        $fakeProvider = new class implements GalleryShowcaseProviderInterface {
            public function getShowcases(): array
            {
                return [];
            }
        };

        $container = new ContainerBuilder();
        $container->register(GalleryShowcaseRegistry::class);
        $container->register('ui.gallery_showcase_provider', $fakeProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        (new GalleryShowcaseProviderPass())->process($container);

        $calls = $container->getDefinition(GalleryShowcaseRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.gallery_showcase_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(GalleryShowcaseRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        (new GalleryShowcaseProviderPass())->process($container);

        $this->assertSame([], $container->getDefinition(GalleryShowcaseRegistry::class)->getMethodCalls());
    }
}
