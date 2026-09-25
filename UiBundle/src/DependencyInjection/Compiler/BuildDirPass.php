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

// Every consumer concatenates "c975l_ui.build_dir" as is, so a value written "bundles/build-demo/" made StylesheetRegistry::isGenerated() look for a "//" prefix and the generated stylesheets lost their "?v=mtime". Trimmed once here rather than in loadExtension(), where the app's own value is not applied yet
class BuildDirPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $buildDir = trim((string) $container->getParameter('c975l_ui.build_dir'), '/');

        if ('' === $buildDir) {
            throw new \InvalidArgumentException('The "c975l_ui.build_dir" parameter must name a directory under public/, not be empty.');
        }

        $container->setParameter('c975l_ui.build_dir', $buildDir);
    }
}
