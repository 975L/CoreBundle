<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\EmailLayoutProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\EmailLayoutProviderPass;
use c975L\UiBundle\Registry\EmailLayoutRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class EmailLayoutProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new EmailLayoutProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service implementing the interface is auto-discovered, no tag needed
    public function testProcessRegistersEveryEmailLayoutProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(EmailLayoutRegistry::class);
        $container->register('ui.email_layout_provider', DummyEmailLayoutProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new EmailLayoutProviderPass()->process($container);

        $calls = $container->getDefinition(EmailLayoutRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.email_layout_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(EmailLayoutRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new EmailLayoutProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(EmailLayoutRegistry::class)->getMethodCalls());
    }
}

class DummyEmailLayoutProvider implements EmailLayoutProviderInterface
{
    public function wrap(string $bodyHtml): string
    {
        return $bodyHtml;
    }
}
