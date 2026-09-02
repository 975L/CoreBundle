<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Twig;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\FormTranslator;
use c975L\UiBundle\Twig\FormTranslationExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\AttributeExtension;

class FormTranslationExtensionTest extends TestCase
{
    // Named apart from Symfony's own form_label(), which every form theme calls with a FormView
    public function testGetFunctionsRegistersUiFormLabelAlone(): void
    {
        $names = [];
        foreach (new AttributeExtension(FormTranslationExtension::class)->getFunctions() as $function) {
            $names[] = $function->getName();
        }

        $this->assertSame(['ui_form_label'], $names);
    }

    // A calculator's results are printed by the template itself, where the fields go through FormSubmissionType - so the label has to be readable from Twig
    public function testGetLabelReadsAnOutputThroughTheTranslator(): void
    {
        $output = new FormOutput();
        $output->setLabel('Litres');

        $formTranslator = $this->createMock(FormTranslator::class);
        $formTranslator->expects($this->once())
            ->method('getLabel')
            ->with($output)
            ->willReturn('Litres');

        $this->assertSame('Litres', new FormTranslationExtension($formTranslator)->getLabel($output));
    }

    public function testGetLabelReadsAFieldThroughTheTranslator(): void
    {
        $field = new FormField();
        $field->setLabel('Votre nom');

        $formTranslator = $this->createMock(FormTranslator::class);
        $formTranslator->expects($this->once())
            ->method('getLabel')
            ->with($field)
            ->willReturn('Your name');

        $this->assertSame('Your name', new FormTranslationExtension($formTranslator)->getLabel($field));
    }

    // A language saying nothing about a row answers null, which the template renders as the source it already holds
    public function testGetLabelPassesOnTheTranslatorNull(): void
    {
        $formTranslator = $this->createStub(FormTranslator::class);
        $formTranslator->method('getLabel')->willReturn(null);

        $this->assertNull(new FormTranslationExtension($formTranslator)->getLabel(new FormField()));
    }
}
