<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\DependencyInjection\Compiler;

use c975L\UiBundle\Contract\PdfDocumentSourceInterface;
use c975L\UiBundle\DependencyInjection\Compiler\PdfDocumentSourcePass;
use c975L\UiBundle\Registry\PdfDocumentRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class PdfDocumentSourcePassTest extends TestCase
{
    public function testProcessDoesNothingWhenRegistryIsNotRegistered(): void
    {
        $container = new ContainerBuilder();

        new PdfDocumentSourcePass()->process($container);

        $this->addToAssertionCount(1);
    }

    // Any service whose class implements PdfDocumentSourceInterface is auto-discovered, no tag needed
    public function testProcessRegistersEveryPdfDocumentSourceImplementation(): void
    {
        $fakeSource = new class implements PdfDocumentSourceInterface {
            public function getPdfDocuments(): array
            {
                return [];
            }
        };

        $container = new ContainerBuilder();
        $container->register(PdfDocumentRegistry::class);
        $container->register('ui.pdf_document_source', $fakeSource::class);
        $container->register('unrelated.service', \stdClass::class);

        new PdfDocumentSourcePass()->process($container);

        $calls = $container->getDefinition(PdfDocumentRegistry::class)->getMethodCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('addProvider', $calls[0][0]);
        $this->assertEquals(new Reference('ui.pdf_document_source'), $calls[0][1][0]);
    }

    // Services referencing classes unavailable in prod (require-dev-only packages) must not break the pass
    public function testProcessSkipsDefinitionsWithUnresolvableClasses(): void
    {
        $container = new ContainerBuilder();
        $container->register(PdfDocumentRegistry::class);
        $container->register('broken.service', 'This\\Class\\Does\\Not\\Exist');

        new PdfDocumentSourcePass()->process($container);

        $this->assertSame([], $container->getDefinition(PdfDocumentRegistry::class)->getMethodCalls());
    }
}
