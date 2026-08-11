<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection;

use c975L\UiBundle\DependencyInjection\Compiler\BlockRegistryPass;
use c975L\UiBundle\Registry\BlockRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Contracts\Translation\TranslatorInterface;

// Three services.yaml tags enforce columns-only slots, and none of them fails loudly on its own
class FlexColumnsSlotContextTest extends TestCase
{
    public function testTheRowsSlotsOfferNothingButTheColumnKind(): void
    {
        $offered = $this->kindsOfferedIn(BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT);

        $this->assertSame(['flex_column'], $offered);
    }

    // The same rule seen from BlockMoveController's side, which validates a drag through this method
    public function testAnOrdinaryKindMayNotBeDroppedStraightIntoTheRow(): void
    {
        $registry = $this->registry();

        $this->assertFalse($registry->isAllowedInContext('text_section', BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT));
        $this->assertTrue($registry->isAllowedInContext('flex_column', BlockRegistry::FLEX_COLUMNS_SLOT_CONTEXT));
    }

    // The other container sharing the default slot context must stay open to every kind
    public function testTheCardsContainerKeepsItsOpenSlotContext(): void
    {
        $registry = $this->registry();
        $offered = $this->kindsOfferedIn(BlockRegistry::SLOT_CONTEXT);

        $this->assertSame(BlockRegistry::SLOT_CONTEXT, $registry->getSlotContext('section_cards'));
        $this->assertContains('card', $offered);
        $this->assertGreaterThan(1, count($offered));
    }

    // A column's own slots stay one context further down, so no third level of nesting is ever possible
    public function testTheColumnsOwnSlotsStayOnTheNestedContext(): void
    {
        $this->assertSame(BlockRegistry::NESTED_SLOT_CONTEXT, $this->registry()->getSlotContext('flex_column'));
        $this->assertNotContains('flex_column', $this->kindsOfferedIn(BlockRegistry::NESTED_SLOT_CONTEXT));
    }

    /**
     * @return array<int, string>
     */
    private function kindsOfferedIn(string $context): array
    {
        $kinds = [];
        foreach ($this->registry()->groupedByCategory($context) as $group) {
            $kinds = array_merge($kinds, array_values($group));
        }

        sort($kinds);

        return $kinds;
    }

    // The bundle's real block declarations replayed onto a real registry, as the compiler pass does
    private function registry(): BlockRegistry
    {
        $container = new ContainerBuilder();
        new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/config'))->load('services.yaml');
        new BlockRegistryPass()->process($container);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key) => $key);

        $registry = new BlockRegistry($translator);
        foreach ($container->getDefinition(BlockRegistry::class)->getMethodCalls() as [$method, $arguments]) {
            $registry->{$method}(...$arguments);
        }

        return $registry;
    }
}
