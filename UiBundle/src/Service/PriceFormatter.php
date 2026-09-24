<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\FormField;

// The one place an amount of money is written, so a calculator's results and the prices shown in its fields' labels read the same "1 490 €" - see ExpressionEvaluator and FormField::$price
class PriceFormatter
{
    // An amount in the currency the locale names, with the given decimals, or as many as it carries up to two when none is given: a 2.38 € binding keeps its cents where a 1 490 € site shows none
    public function format(float $value, string $locale, ?int $decimals = null): string
    {
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
        $formatter->setAttribute(\NumberFormatter::MIN_FRACTION_DIGITS, $decimals ?? 0);
        $formatter->setAttribute(\NumberFormatter::MAX_FRACTION_DIGITS, $decimals ?? 2);

        // The currency the locale itself names when it names one - a plain "fr" (no region, which is what a c975L site runs on) answers "XXX", the code for "no currency", and would print an "¤" placeholder. Euro is the fallback rather than a site-wide setting nobody would change; a calculator quoting anything else states it as a plain number with its own unit
        $currency = $formatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE);

        return $formatter->formatCurrency($value, in_array($currency, ['', 'XXX', false], true) ? 'EUR' : $currency);
    }

    // A field's label followed by its price, "Création du logo (400 €)" - the label alone when the field has none, and for a choice, whose options carry the amounts instead (see optionLabel())
    public function label(FormField $field, string $label, string $locale): string
    {
        return null === $field->getPrice() || FormField::TYPE_CHOICE === $field->getType() ? $label : $this->withAmount($label, $field->getPrice(), $locale);
    }

    // An option of a priced choice followed by what it adds, its value times the field's price - "Site vitrine (1 490 €)" for a price of 1. The label alone when the field has no price, the option no number, or when it adds nothing: "Thème adapté (0 €)" reads as a mistake
    public function optionLabel(FormField $field, string $label, string $value, string $locale): string
    {
        $amount = null === $field->getPrice() || !is_numeric($value) ? 0.0 : (float) $value * $field->getPrice();

        return 0.0 === $amount ? $label : $this->withAmount($label, $amount, $locale);
    }

    private function withAmount(string $label, float $amount, string $locale): string
    {
        return sprintf('%s (%s)', $label, $this->format($amount, $locale));
    }
}
