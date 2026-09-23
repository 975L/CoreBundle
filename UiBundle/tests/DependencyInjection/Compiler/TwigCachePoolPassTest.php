<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\TwigCachePoolPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class TwigCachePoolPassTest extends TestCase
{
    // One pool for the blocks and the {% cache %} fragments, so a bundle emptying its tag reaches both
    public function testTwigsCachePoolBecomesTheTaggableAppPool(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('twig.cache', new Definition(TagAwareAdapter::class));
        $container->setDefinition('cache.app.taggable', new Definition(TagAwareAdapter::class));

        new TwigCachePoolPass()->process($container);

        $this->assertTrue($container->hasAlias('twig.cache'));
        $this->assertSame('cache.app.taggable', (string) $container->getAlias('twig.cache'));
    }

    // A site without twig/extra-bundle's cache extension has nothing to point anywhere
    public function testNothingHappensWithoutTwigsCachePool(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cache.app.taggable', new Definition(TagAwareAdapter::class));

        new TwigCachePoolPass()->process($container);

        $this->assertFalse($container->has('twig.cache'));
    }
}
