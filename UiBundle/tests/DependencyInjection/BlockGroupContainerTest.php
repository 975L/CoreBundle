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

// The chromeless container: what makes it worth having is where it can be picked and what it may hold, both of which are services.yaml tags nothing else checks
class BlockGroupContainerTest extends TestCase
{
    public function testTheGroupIsAContainerOnTheDefaultSlotContext(): void
    {
        $registry = $this->registry();

        $this->assertTrue($registry->isContainer('block_group'));
        $this->assertSame(BlockRegistry::SLOT_CONTEXT, $registry->getSlotContext('block_group'));
    }

    // Declaring no slot context of its own is the depth guard: containers are excluded from that context, so a group can never hold another one
    public function testAGroupMayNotHoldAnotherGroup(): void
    {
        $this->assertFalse($this->registry()->isAllowedInContext('block_group', BlockRegistry::SLOT_CONTEXT));
    }

    // Grouping a menu's items was what this was first reached for, and a menu now has a group of its own (SiteBundle's "menu_group", built with a slot context a menu link opts into). This one's slots stay on the default context, where no menu link is ever allowed: offered in a menu, it would be a group whose every drop is refused
    public function testTheGroupStaysOutOfAMenu(): void
    {
        $this->assertFalse($this->registry()->isAllowedInContext('block_group', BlockRegistry::MENU_CONTEXT));
    }

    // A navbar stays a plain list of links, group or no group
    public function testTheGroupStaysOutOfANavbar(): void
    {
        $this->assertFalse($this->registry()->isAllowedInContext('block_group', BlockRegistry::MENU_NAVBAR_CONTEXT));
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
