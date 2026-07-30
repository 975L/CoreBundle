<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection;

use c975L\UiBundle\Service\BlockCacheClearer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Compiler\ResolveInstanceofConditionalsPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;

// BlockCacheClearer carries no tag: it relies on autoconfigure, invisible from the class itself
class BlockCacheClearerRegistrationTest extends TestCase
{
    public function testTheClearerIsAutoconfiguredIntoTheKernelCacheClearerTag(): void
    {
        $container = $this->buildContainer();

        $this->assertTrue($container->hasDefinition(BlockCacheClearer::class), 'BlockCacheClearer is not registered at all - is Service/ still covered by the resource glob?');
        $this->assertArrayHasKey('kernel.cache_clearer', $container->getDefinition(BlockCacheClearer::class)->getTags());
    }

    private function buildContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        // Exactly what FrameworkBundle's extension does, so the test asserts the real mechanism rather than a tag written by hand
        $container->registerForAutoconfiguration(CacheClearerInterface::class)->addTag('kernel.cache_clearer');

        (new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config')))->load('services.yaml');
        (new ResolveInstanceofConditionalsPass())->process($container);

        return $container;
    }
}
