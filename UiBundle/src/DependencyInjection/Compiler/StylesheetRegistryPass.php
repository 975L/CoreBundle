<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;
use c975L\UiBundle\Registry\StylesheetRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class StylesheetRegistryPass implements CompilerPassInterface
{
    // Below any priority a bundle would give itself, so an auto-tagged provider lands last whatever the bundles around it declare - rather than relying on 0 happening to be the floor of their range
    private const AUTO_TAG_PRIORITY = -100;

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(StylesheetRegistry::class)) {
            return;
        }

        $registry = $container->getDefinition(StylesheetRegistry::class);

        $this->tagUntaggedProviders($container);

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

    // A bundle tags its own provider in services.yaml, with a priority saying where its sheet belongs in the cascade. An app can't: its services.yaml is its own file, which no scaffold may edit - so a service merely implementing the interface is tagged here, below every bundle's range. That is exactly where a site's theme has to load, so the two facts agree by construction
    private function tagUntaggedProviders(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            $class = $definition->getClass();
            if (!$class || $definition->hasTag('ui.stylesheet')) {
                continue;
            }

            try {
                // Same guard as AbstractProviderPass: a vendor service may name a class whose interfaces come from a require-dev-only package, absent in a --no-dev install
                if (is_subclass_of($class, BundleStylesheetProviderInterface::class)) {
                    $definition->addTag('ui.stylesheet', ['priority' => self::AUTO_TAG_PRIORITY]);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
