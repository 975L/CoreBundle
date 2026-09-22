<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\AiSearchCardProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\AiSearchCardProviderPass;
use c975L\UiBundle\Registry\AiSearchCardRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AiSearchCardProviderPassTest extends TestCase
{
    // Any service whose class implements AiSearchCardProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryCardProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(AiSearchCardRegistry::class);
        $container->register('shop.ai_search_card_provider', DummyAiSearchCardProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new AiSearchCardProviderPass()->process($container);

        $calls = $container->getDefinition(AiSearchCardRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('shop.ai_search_card_provider'), $calls[0][1][0]);
    }
}

class DummyAiSearchCardProvider implements AiSearchCardProviderInterface
{
    public function renderCards(array $urls): array
    {
        return [];
    }
}
