<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\EmailAttachmentProviderInterface;
use c975L\UiBundle\DependencyInjection\Compiler\EmailAttachmentProviderPass;
use c975L\UiBundle\Model\EmailAttachment;
use c975L\UiBundle\Registry\EmailAttachmentRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FakeEmailAttachmentProvider implements EmailAttachmentProviderInterface
{
    public function getAttachmentKinds(): array
    {
        return [];
    }

    public function createAttachment(string $kind, array $context): ?EmailAttachment
    {
        return null;
    }
}

class EmailAttachmentProviderPassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new EmailAttachmentProviderPass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements EmailAttachmentProviderInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryEmailAttachmentProviderImplementation(): void
    {
        $container = new ContainerBuilder();
        $container->register(EmailAttachmentRegistry::class);
        $container->register('shop.invoice_attachment_provider', FakeEmailAttachmentProvider::class);
        $container->register('unrelated.service', \stdClass::class);

        new EmailAttachmentProviderPass()->process($container);

        $calls = $container->getDefinition(EmailAttachmentRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('shop.invoice_attachment_provider'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(EmailAttachmentRegistry::class);
        $container->register('broken.service', 'This\Class\Does\Not\Exist');

        new EmailAttachmentProviderPass()->process($container);

        $this->assertSame([], $container->getDefinition(EmailAttachmentRegistry::class)->getMethodCalls());
    }
}
