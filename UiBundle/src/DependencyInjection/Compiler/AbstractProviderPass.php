<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
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
            // An abstract definition is no service: Symfony writes one per class matched by an instanceof conditional, and a reference to it is refused outright (see CheckReferenceValidityPass)
            if ($definition->isAbstract()) {
                continue;
            }

            $class = self::resolveClass($container, $definition);
            if (!$class) {
                continue;
            }

            try {
                // Some vendor services (e.g. Symfony's translation extractor visitors) reference classes whose interfaces come from require-dev-only packages (e.g. nikic/php-parser), not installed in prod (--no-dev)
                if (is_subclass_of($class, $this->interface)) {
                    $registry->addMethodCall('addProvider', [new Reference($id)]);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    // The class the service is written with, read off its parent when it carries none of its own: a service matched by an instanceof conditional - anything implementing ResetInterface, say - stays a child definition until Symfony merges the two, which only happens after this pass has run (ResolveChildDefinitionsPass)
    private static function resolveClass(ContainerBuilder $container, Definition $definition): ?string
    {
        while (null === $definition->getClass() && $definition instanceof ChildDefinition) {
            $parent = $definition->getParent();
            if (!$container->hasDefinition($parent)) {
                return null;
            }

            $definition = $container->getDefinition($parent);
        }

        return $definition->getClass();
    }
}
