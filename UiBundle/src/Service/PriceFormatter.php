<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

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

    // A field's label followed by its price, "Création du logo (400 €)" - the label alone when the field has none
    public function label(string $label, ?float $price, string $locale): string
    {
        return null === $price ? $label : sprintf('%s (%s)', $label, $this->format($price, $locale));
    }
}
