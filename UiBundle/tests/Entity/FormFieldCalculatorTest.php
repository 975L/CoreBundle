<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Entity;

use c975L\UiBundle\Entity\FormField;
use PHPUnit\Framework\TestCase;

// What a FormField gains once it can feed a calculator: a variable name an expression can name, and the bounds/options a slider or a choice list needs
class FormFieldCalculatorTest extends TestCase
{
    public function testOnlyTheNumericTypesCanFeedAnExpression(): void
    {
        foreach ([FormField::TYPE_NUMBER, FormField::TYPE_RANGE, FormField::TYPE_CHOICE] as $type) {
            $this->assertTrue(new FormField()->setType($type)->isNumeric(), $type);
        }

        foreach ([FormField::TYPE_TEXT, FormField::TYPE_EMAIL, FormField::TYPE_CHECKBOX, FormField::TYPE_DATE] as $type) {
            $this->assertFalse(new FormField()->setType($type)->isNumeric(), $type);
        }
    }

    // A slugged name carries dashes, which are subtractions to any expression parser
    public function testTheVariableNameTurnsTheSlugDashesIntoUnderscores(): void
    {
        $this->assertSame('prix_de_l_essence', new FormField()->setName('prix-de-l-essence')->getVariableName());
    }

    // "95 sans plomb" slugs to "95-sans-plomb", and no expression variable may start with a digit
    public function testAVariableNameNeverStartsWithADigit(): void
    {
        $this->assertSame('f_95_sans_plomb', new FormField()->setName('95-sans-plomb')->getVariableName());
    }

    public function testOptionsAreEditedAsOneLinePerOption(): void
    {
        $field = new FormField()->setOptionsText("Véhicule léger|1.15\nGros véhicule|1.25");

        $this->assertSame([
            ['label' => 'Véhicule léger', 'value' => '1.15'],
            ['label' => 'Gros véhicule', 'value' => '1.25'],
        ], $field->getOptions());
    }

    // Typed with no separator, the option labels itself - which is what a plain list of values is
    public function testAnOptionWithNoSeparatorIsLabelledByItsOwnValue(): void
    {
        $this->assertSame([['label' => '1.15', 'value' => '1.15']], new FormField()->setOptionsText('1.15')->getOptions());
    }

    public function testEmptyLinesAreDroppedAndTheTextRoundTrips(): void
    {
        $field = new FormField()->setOptionsText("A|1\n\n  \nB|2\n");

        $this->assertSame("A|1\nB|2", $field->getOptionsText());
    }

    public function testNoOptionAtAllIsStoredAsNullRatherThanAnEmptyArray(): void
    {
        $field = new FormField()->setOptionsText('   ');

        $this->assertSame([], $field->getOptions());
        $this->assertNull($field->getOptionsText());
    }
}
