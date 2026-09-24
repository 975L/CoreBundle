<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Service;

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
        $this->assertSame("Création du logo (400\u{00A0}€)", new PriceFormatter()->label('Création du logo', 400, 'fr'));
    }

    public function testALabelWithoutPriceIsLeftAlone(): void
    {
        $this->assertSame('Création du logo', new PriceFormatter()->label('Création du logo', null, 'fr'));
    }
}
