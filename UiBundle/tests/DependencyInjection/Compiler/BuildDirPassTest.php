<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\DependencyInjection\Compiler\BuildDirPass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class BuildDirPassTest extends TestCase
{
    // Edge slashes go, so no consumer ever builds a "//" path
    public function testEdgeSlashesAreTrimmed(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('c975l_ui.build_dir', '/bundles/build-demo/');

        new BuildDirPass()->process($container);

        $this->assertSame('bundles/build-demo', $container->getParameter('c975l_ui.build_dir'));
    }

    // A well-written value comes out untouched
    public function testCleanValueIsKept(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('c975l_ui.build_dir', 'bundles/build');

        new BuildDirPass()->process($container);

        $this->assertSame('bundles/build', $container->getParameter('c975l_ui.build_dir'));
    }

    // Nothing left once trimmed would write the stylesheets straight into public/
    public function testEmptyValueFails(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('c975l_ui.build_dir', '/');

        $this->expectException(\InvalidArgumentException::class);

        new BuildDirPass()->process($container);
    }
}
