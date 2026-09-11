<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\InternalLinkLocalizerInterface;
use c975L\UiBundle\DependencyInjection\Compiler\InternalLinkLocalizerPass;
use c975L\UiBundle\Registry\InternalLinkLocalizerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

// The localizers live in the bundles owning the urls - SiteBundle its pages - so the discovery is proved here against a stand-in
class FakeInternalLinkLocalizer implements InternalLinkLocalizerInterface
{
    #[\Override]
    public function localize(string $value): string
    {
        return $value;
    }
}

class InternalLinkLocalizerPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new InternalLinkLocalizerPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements InternalLinkLocalizerInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryLocalizerImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(InternalLinkLocalizerRegistry::class);
        $container->register('ui.internal_link_localizer', FakeInternalLinkLocalizer::class);
        $container->register('unrelated.service', \stdClass::class);

        new InternalLinkLocalizerPass()->process($container);

        $calls = $container->getDefinition(InternalLinkLocalizerRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.internal_link_localizer'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(InternalLinkLocalizerRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new InternalLinkLocalizerPass()->process($container);

        $this->assertSame([], $container->getDefinition(InternalLinkLocalizerRegistry::class)->getMethodCalls());
    }
}
