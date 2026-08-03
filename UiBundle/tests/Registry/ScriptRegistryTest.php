<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Registry;

use c975L\UiBundle\Contract\BundleScriptProviderInterface;
use c975L\UiBundle\Registry\ScriptRegistry;
use PHPUnit\Framework\TestCase;

class ScriptRegistryTest extends TestCase
{
    private function createProvider(array $scripts): BundleScriptProviderInterface
    {
        $provider = $this->createStub(BundleScriptProviderInterface::class);
        $provider->method('getScripts')->willReturn($scripts);

        return $provider;
    }

    public function testAllReturnsEmptyArrayWhenNoProviders(): void
    {
        $registry = new ScriptRegistry();

        $this->assertSame([], $registry->all());
    }

    public function testAllMergesScriptsFromEveryProvider(): void
    {
        $registry = new ScriptRegistry();
        $registry->addProvider($this->createProvider(['a.js']));
        $registry->addProvider($this->createProvider(['b.js']));

        $this->assertSame(['a.js', 'b.js'], $registry->all());
    }

    // A script contributed by two different providers must appear only once, in declaration order
    public function testAllDeduplicatesScriptsAcrossProviders(): void
    {
        $registry = new ScriptRegistry();
        $registry->addProvider($this->createProvider(['a.js', 'b.js']));
        $registry->addProvider($this->createProvider(['b.js', 'c.js']));

        $this->assertSame(['a.js', 'b.js', 'c.js'], $registry->all());
    }
}
