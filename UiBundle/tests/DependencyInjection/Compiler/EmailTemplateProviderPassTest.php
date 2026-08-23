<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\EmailTemplateProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\EmailTemplateProviderPass;
use c975L\UiBundle\Registry\EmailTemplateProviderRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeEmailTemplateProvider implements EmailTemplateProviderInterface
{
    public function getEmailTemplates(): array
    {
        return [];
    }
}

class EmailTemplateProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new EmailTemplateProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements EmailTemplateProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryEmailTemplateProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(EmailTemplateProviderRegistry::class);
        $container->register('shop.email_template_provider', FakeEmailTemplateProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new EmailTemplateProviderPass()->process($container);

        $calls = $container->getDefinition(EmailTemplateProviderRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('shop.email_template_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(EmailTemplateProviderRegistry::class);
        $container->register('broken.service', 'This\Class\Does\Not\Exist');

        new EmailTemplateProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(EmailTemplateProviderRegistry::class)->getMethodCalls());
    }
}
