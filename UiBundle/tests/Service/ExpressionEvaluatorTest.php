<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Service\CalculatorExpressionLanguage;
use c975L\UiBundle\Service\ExpressionEvaluator;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExpressionEvaluatorTest extends TestCase
{
    private function createEvaluator(string $locale = 'fr'): ExpressionEvaluator
    {
        $translator = $this->createStub(TranslatorInterface::class);
        // The key, plus whatever was substituted into it: a lint message now carries the name that was mistyped, the one it suggests instead or the parser's own wording, and a stub answering the key alone would hide all three
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = []): string => [] === $parameters ? $id : $id . ' ' . implode(' ', $parameters)
        );
        $translator->method('getLocale')->willReturn($locale);

        return new ExpressionEvaluator(new CalculatorExpressionLanguage(), $translator);
    }

    private function createField(string $name, string $type, ?string $default = null): FormField
    {
        $field = new FormField();
        $field->setLabel($name)->setName($name)->setType($type)->setDefaultValue($default);

        return $field;
    }

    private function createOutput(string $name, string $expression, bool $visible = true): FormOutput
    {
        $output = new FormOutput();
        $output->setLabel($name)->setName($name)->setExpression($expression)->setVisible($visible);

        return $output;
    }

    public function testVariableNamesHoldTheNumericFieldsAndEveryOutput(): void
    {
        $form = new Form();
        $form->addField($this->createField('km-an', FormField::TYPE_RANGE));
        $form->addField($this->createField('message', FormField::TYPE_TEXTAREA));
        $form->addOutput($this->createOutput('litres-an', 'km_an / 100'));

        // The dashes a slug carries are underscores here, a dash being a subtraction to the parser - and the textarea is nowhere in sight, having no number to offer
        $this->assertSame(['km_an', 'litres_an'], $this->createEvaluator()->variableNames($form));
    }

    public function testComputeChainsOutputsInOrderAndReadsTheSubmittedValues(): void
    {
        $form = new Form();
        $form->addField($this->createField('km-an', FormField::TYPE_RANGE, '15000'));
        $form->addField($this->createField('conso', FormField::TYPE_NUMBER, '7'));
        $form->addOutput($this->createOutput('litres-an', 'km_an / 100 * conso', false));
        $form->addOutput($this->createOutput('budget', 'litres_an * 1.85'));

        $results = $this->createEvaluator()->compute($form, ['km-an' => '20000', 'conso' => '8']);

        $this->assertSame(1600.0, $results['litres-an']['value']);
        $this->assertSame(2960.0, $results['budget']['value']);
    }

    public function testComputeFallsBackToTheFieldDefaultThenToZero(): void
    {
        $form = new Form();
        $form->addField($this->createField('conso', FormField::TYPE_NUMBER, '7'));
        $form->addField($this->createField('bonus', FormField::TYPE_NUMBER));
        $form->addOutput($this->createOutput('total', 'conso + bonus'));

        // Nothing submitted at all is what the very first render hands over, and it still has to produce a number
        $this->assertSame(7.0, $this->createEvaluator()->compute($form, [])['total']['value']);
    }

    // A range with no default renders at the middle of its span and a choice on its first option, so the first render has to compute on those rather than on 0 - a visitor without JavaScript never gets a second chance to correct the figures
    public function testComputeStartsARangeAtItsMiddleAndAChoiceOnItsFirstOption(): void
    {
        $range = $this->createField('litres', FormField::TYPE_RANGE);
        $range->setMinValue(10.0)->setMaxValue(50.0);
        $choice = $this->createField('coefficient', FormField::TYPE_CHOICE);
        $choice->setOptions([['label' => 'Véhicule léger', 'value' => '2'], ['label' => 'Poids lourd', 'value' => '5']]);

        $form = new Form();
        $form->addField($range);
        $form->addField($choice);
        $form->addOutput($this->createOutput('total', 'litres * coefficient'));

        $this->assertSame(60.0, $this->createEvaluator()->compute($form, [])['total']['value']);
    }

    // The browser's own defaults when the admin set no bounds
    public function testARangeWithoutBoundsStartsAtFifty(): void
    {
        $form = new Form();
        $form->addField($this->createField('litres', FormField::TYPE_RANGE));
        $form->addOutput($this->createOutput('total', 'litres'));

        $this->assertSame(50.0, $this->createEvaluator()->compute($form, [])['total']['value']);
    }

    public function testComputeReturnsNullRatherThanFailingOnADivisionByZero(): void
    {
        $form = new Form();
        $form->addField($this->createField('reservoir', FormField::TYPE_RANGE, '0'));
        $form->addOutput($this->createOutput('pleins', '100 / reservoir'));

        $results = $this->createEvaluator()->compute($form, []);

        $this->assertNull($results['pleins']['value']);
        $this->assertNull($results['pleins']['formatted']);
    }

    public function testAFailedOutputDoesNotBreakTheOnesAfterIt(): void
    {
        $form = new Form();
        $form->addField($this->createField('conso', FormField::TYPE_NUMBER, '7'));
        $form->addOutput($this->createOutput('broken', '1 / 0'));
        $form->addOutput($this->createOutput('after', 'broken + conso'));

        $results = $this->createEvaluator()->compute($form, []);

        $this->assertNull($results['broken']['value']);
        $this->assertSame(7.0, $results['after']['value']);
    }

    public function testCurrencyOutputIsFormattedInTheCurrentLocale(): void
    {
        $form = new Form();
        $form->addField($this->createField('prix', FormField::TYPE_NUMBER, '1234.5'));
        $output = $this->createOutput('total', 'prix');
        $output->setFormat(FormOutput::FORMAT_CURRENCY)->setDecimals(0);
        $form->addOutput($output);

        $formatted = (string) $this->createEvaluator()->compute($form, [])['total']['formatted'];

        // The separators and the symbol's place are the locale's business, only the currency itself is asserted here
        $this->assertStringContainsString('€', $formatted);
        $this->assertStringNotContainsString('.5', $formatted);
    }

    public function testUnitIsAppendedAfterTheFormattedNumber(): void
    {
        $form = new Form();
        $form->addField($this->createField('litres', FormField::TYPE_NUMBER, '42'));
        $output = $this->createOutput('total', 'litres');
        $output->setUnit(' L');
        $form->addOutput($output);

        $this->assertStringEndsWith(' L', (string) $this->createEvaluator()->compute($form, [])['total']['formatted']);
    }

    public function testLintRefusesAnEmptyExpression(): void
    {
        $this->assertSame('text.expression_empty', $this->createEvaluator()->lint('  ', ['a']));
    }

    public function testLintRefusesADecimalComma(): void
    {
        $this->assertSame('text.expression_decimal_comma', $this->createEvaluator()->lint('a * 1,15', ['a']));
    }

    public function testLintAcceptsACommaSeparatingTwoArguments(): void
    {
        $this->assertNull($this->createEvaluator()->lint('round(a, 2)', ['a']));
    }

    public function testLintRefusesAStringLiteralAndAnythingElseOutsideArithmetic(): void
    {
        $this->assertSame('text.expression_forbidden_characters', $this->createEvaluator()->lint('a ~ "x"', ['a']));
    }

    public function testLintRefusesAVariableTheFormDoesNotDeclare(): void
    {
        // Exactly what a relabelled field costs: the slug moves, and the formula that read it no longer resolves
        $this->assertNotNull($this->createEvaluator()->lint('prix_e85 * 2', ['prix_sp95']));
    }

    public function testLintAcceptsTheSixWhitelistedFunctions(): void
    {
        foreach (CalculatorExpressionLanguage::FUNCTIONS as $function) {
            $this->assertNull($this->createEvaluator()->lint(sprintf('%s(a, 2)', $function), ['a']), $function);
        }
    }

    public function testConstantIsNotReachableFromAnExpression(): void
    {
        // Symfony registers constant() and enum() by default: both would let an admin-typed formula read outside the values handed to it, and CalculatorExpressionLanguage drops them by never calling the parent. Written without quotes on purpose, so what fails is the missing function and not the character allowlist
        $this->assertStringContainsString('constant', (string) $this->createEvaluator()->lint('constant(a)', ['a']));
        $this->assertStringContainsString('enum', (string) $this->createEvaluator()->lint('enum(a)', ['a']));
    }

    // A name the Form does not declare is caught before the parser, so what the admin reads is this bundle's own wording and not the component's English - with the name they most likely meant beside it
    public function testLintNamesTheClosestVariableToTheOneThatWasMistyped(): void
    {
        $message = (string) $this->createEvaluator()->lint('litres_de_sp95_par_am * 2', ['litres_de_sp95_par_an', 'budget_e85']);

        $this->assertStringStartsWith('text.expression_unknown_variable_suggestion', $message);
        $this->assertStringContainsString('litres_de_sp95_par_am', $message);
        $this->assertStringContainsString('litres_de_sp95_par_an', $message);
    }

    // Nothing close enough is worse than nothing at all: a suggestion the admin never typed sends them looking for a variable that has no bearing on their mistake
    public function testLintSuggestsNothingWhenNoDeclaredNameIsClose(): void
    {
        $this->assertSame(
            'text.expression_unknown_variable toto',
            $this->createEvaluator()->lint('toto * 2', ['litres_de_sp95_par_an'])
        );
    }

    // The six functions are called by name and followed by "(", which is what tells them from a variable - a check reading them as one would refuse every formula that rounds
    public function testLintDoesNotReadAFunctionNameAsAVariable(): void
    {
        $this->assertNull($this->createEvaluator()->lint('round(max(a, 2) / 3, 1)', ['a']));
    }

    // Counted rather than left to the parser, whose own message names a character position instead of saying which way the brackets fail to match
    public function testLintSaysWhichWayTheBracketsAreUnbalanced(): void
    {
        $this->assertSame('text.expression_unclosed_parenthesis 1', $this->createEvaluator()->lint('(a + 2', ['a']));
        $this->assertSame('text.expression_unopened_parenthesis 1', $this->createEvaluator()->lint('a + 2)', ['a']));
    }

    // What none of the three checks above catch is still led in the admin's own language, the parser's wording kept behind it rather than dropped
    public function testLintLeadsInItsOwnWordingAndKeepsTheParsersDetail(): void
    {
        $message = (string) $this->createEvaluator()->lint('a * * 2', ['a']);

        $this->assertStringStartsWith('text.expression_not_understood', $message);
        $this->assertStringContainsString('position', $message);
    }
}
