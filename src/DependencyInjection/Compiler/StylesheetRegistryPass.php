<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\DependencyInjection\Compiler;

use c975L\UiBundle\Registry\StylesheetRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class StylesheetRegistryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(StylesheetRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(StylesheetRegistry::class);

        $tagged = $container->findTaggedServiceIds('ui.stylesheet');

        $sorted = [];
        foreach ($tagged as $id => $tags) {
            $sorted[] = ['id' => $id, 'priority' => (int) ($tags[0]['priority'] ?? 0)];
        }
        usort($sorted, fn ($a, $b) => $b['priority'] <=> $a['priority']);

        foreach ($sorted as $entry) {
            $registry->addMethodCall('addProvider', [new Reference($entry['id'])]);
        }
    }
}
