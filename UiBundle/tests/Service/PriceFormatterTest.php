<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Service\PriceFormatter;
use PHPUnit\Framework\TestCase;

// The one writer of an amount, shared by a calculator's results and its fields' labels
class PriceFormatterTest extends TestCase
{
    // A round amount shows no cents and a plain "fr" locale, which names no currency, falls back to euro
    public function testARoundAmountShowsNoCents(): void
    {
        $this->assertSame("1\u{202F}490\u{00A0}€", new PriceFormatter()->format(1490, 'fr'));
    }

    // A binding at 2.38 € keeps its cents
    public function testCentsAreKeptWhenTheAmountHasSome(): void
    {
        $this->assertSame("2,38\u{00A0}€", new PriceFormatter()->format(2.38, 'fr'));
    }

    // An output states its own decimals, which win over the zero-to-two default
    public function testGivenDecimalsAreApplied(): void
    {
        $this->assertSame("400,00\u{00A0}€", new PriceFormatter()->format(400, 'fr', 2));
    }

    public function testTheAmountIsWrittenTheWayTheLocaleWritesIt(): void
    {
        $this->assertSame('€400', new PriceFormatter()->format(400, 'en'));
    }

    public function testALabelIsFollowedByItsPrice(): void
    {
        $field = new FormField()->setType(FormField::TYPE_CHECKBOX)->setPrice(400);

        $this->assertSame("Création du logo (400\u{00A0}€)", new PriceFormatter()->label($field, 'Création du logo', 'fr'));
    }

    public function testALabelWithoutPriceIsLeftAlone(): void
    {
        $this->assertSame('Création du logo', new PriceFormatter()->label(new FormField(), 'Création du logo', 'fr'));
    }

    // A priced choice shows its amounts on its options, never a "(1 €)" after its own label
    public function testAPricedChoiceShowsWhatEachOptionAdds(): void
    {
        $field = new FormField()->setType(FormField::TYPE_CHOICE)->setPrice(1);
        $formatter = new PriceFormatter();

        $this->assertSame('Type de site', $formatter->label($field, 'Type de site', 'fr'));
        $this->assertSame("Site vitrine (1\u{202F}490\u{00A0}€)", $formatter->optionLabel($field, 'Site vitrine', '1490', 'fr'));
        $this->assertSame('Autre', $formatter->optionLabel($field, 'Autre', 'Autre', 'fr'));
        $this->assertSame('Thème adapté', $formatter->optionLabel($field, 'Thème adapté', '0', 'fr'));
        $this->assertSame('2 mois', $formatter->optionLabel(new FormField()->setType(FormField::TYPE_CHOICE), '2 mois', '1', 'fr'));
    }
}
