<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Registry\ScriptRegistry;
use c975L\UiBundle\Twig\ScriptExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class ScriptExtensionTest extends TestCase
{
    public function testGetBundleScriptsDelegatesToRegistryAll(): void
    {
        $registry = $this->createStub(ScriptRegistry::class);
        $registry->method('all')->willReturn(['a.js', 'b.js']);

        $extension = new ScriptExtension($registry);

        $this->assertSame(['a.js', 'b.js'], $extension->getBundleScripts());
    }

    public function testGetFunctionsRegistersBundleScriptsFunction(): void
    {
        $functions = new AttributeExtension(ScriptExtension::class)->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('bundle_scripts', $functions[0]->getName());
    }
}
