<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\CalculatorExpressionLanguage;
use c975L\UiBundle\Service\ExpressionEvaluator;
use c975L\UiBundle\Service\FormFieldNamer;
use c975L\UiBundle\Validator\ValidExpressions;
use c975L\UiBundle\Validator\ValidExpressionsValidator;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ValidExpressionsValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): ValidExpressionsValidator
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $translator->method('getLocale')->willReturn('fr');

        return new ValidExpressionsValidator(new ExpressionEvaluator(new CalculatorExpressionLanguage(), $translator));
    }

    private function createForm(array $fieldLabels, array $outputs): Form
    {
        $form = new Form();
        $form->setName('economies');

        foreach ($fieldLabels as $label) {
            $field = new FormField();
            $field->setLabel($label)->setType(FormField::TYPE_NUMBER);
            $form->addField($field);
        }

        foreach ($outputs as $label => $expression) {
            $output = new FormOutput();
            $output->setLabel($label)->setExpression($expression);
            $form->addOutput($output);
        }

        // The very same naming the admin screen runs before validation - the names are what the expressions are checked against
        new FormFieldNamer(new AsciiSlugger())->nameFields($form);

        return $form;
    }

    public function testAFormWithNoOutputIsNotACalculatorAndIsLeftAlone(): void
    {
        $this->validator->validate($this->createForm(['Message'], []), new ValidExpressions());

        $this->assertNoViolation();
    }

    public function testAValidFormulaOverTheFormOwnFieldsPasses(): void
    {
        $form = $this->createForm(["Prix de l'essence", 'Litres'], ['Budget' => 'prix_de_l_essence * litres']);

        $this->validator->validate($form, new ValidExpressions());

        $this->assertNoViolation();
    }

    public function testAFormulaReadingAVariableThatNoLongerExistsIsRefused(): void
    {
        // What relabelling a field costs: the slug moves and the formula keeps the old name
        $form = $this->createForm(['Prix du SP95'], ['Budget' => 'prix_de_l_essence * 2']);

        $this->validator->validate($form, new ValidExpressions());

        $this->assertViolationOnBudget();
    }

    public function testAnOutputCanOnlyReadTheOutputsPlacedBeforeIt(): void
    {
        $form = $this->createForm(['Litres'], ['Budget' => 'total / 2', 'Total' => 'litres * 2']);

        $this->validator->validate($form, new ValidExpressions());

        // "Budget" comes first and reads "total", which is only declared under it
        $this->assertViolationOnBudget();
    }

    // The parser's own wording is left out of the assertions: it is Symfony's to change, where the message key, the path and the named output are this bundle's contract. The path is the collection, not a row: a deleted output leaves holes in the keys, and the message names the faulty output anyway
    private function assertViolationOnBudget(): void
    {
        $violations = $this->context->getViolations();

        $this->assertCount(1, $violations);
        $this->assertSame('label.expression_invalid', $violations->get(0)->getMessageTemplate());
        $this->assertSame('Budget', $violations->get(0)->getParameters()['%output%']);
        $this->assertSame('property.path.outputs', $violations->get(0)->getPropertyPath());
    }
}
