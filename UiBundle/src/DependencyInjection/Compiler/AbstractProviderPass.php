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
use Symfony\Component\DependencyInjection\Reference;

// Shared by every compiler pass auto-discovering services implementing a given marker interface (no tag needed) and registering each one on a registry's addProvider() - see the concrete subclasses (BlockFixtureProviderPass, WhatsNewProviderPass...) for each interface/registry pair
abstract class AbstractProviderPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $interface,
        private readonly string $registryClass,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has($this->registryClass)) {
            return;
        }

        $registry = $container->getDefinition($this->registryClass);

        foreach ($container->getDefinitions() as $id => $definition) {
            $class = $definition->getClass();
            if (!$class) {
                continue;
            }

            try {
                // Some vendor services (e.g. Symfony's translation extractor visitors) reference classes whose interfaces come from require-dev-only packages (e.g. nikic/php-parser), not installed in prod (--no-dev)
                if (is_subclass_of($class, $this->interface)) {
                    $registry->addMethodCall('addProvider', [new Reference($id)]);
                }
            } catch (\Throwable $e) {
                continue;
            }
        }
    }
}
