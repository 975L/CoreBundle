<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

// Twig's {% cache %} writes into a "twig.cache" pool of its own (twig/extra-bundle), where every c975L bundle empties its tags in "cache.app.taggable": a fragment tagged "shop_products" would never hear a product saved. Pointed at the same pool, one invalidation reaches the blocks and the fragments alike
class TwigCachePoolPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('twig.cache') && $container->has('cache.app.taggable')) {
            $container->removeDefinition('twig.cache');
            $container->setAlias('twig.cache', 'cache.app.taggable');
        }
    }
}
