<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Contracts\Translation\TranslatorInterface;

// Evaluates a calculator Form's FormOutput expressions, in their own order, each one seeing the Form's numeric fields plus the outputs already computed before it - the one place a formula an admin typed is ever turned into a number, PHP-side and PHP-side only, so a later JS evaluator would have this grammar to match and not the other way round
class ExpressionEvaluator
{
    // Everything the grammar admits, and nothing else: digits, a decimal point, identifiers, the five arithmetic operators, parentheses and the argument comma. Checked before the parser, which would happily accept string literals, "a.b" property access or "matches" - all pointless in a calculator, and all things a JS parser would then have to implement too
    private const string ALLOWED_CHARACTERS = '/^[0-9A-Za-z_\s+\-*\/%(),.]*$/';

    public function __construct(
        private readonly CalculatorExpressionLanguage $expressionLanguage,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // Every variable an expression of this Form may name: its numeric fields, plus its outputs
    /** @return list<string> */
    public function variableNames(Form $form): array
    {
        $names = [];
        foreach ($form->getFields() as $field) {
            if ($field->isNumeric()) {
                $names[] = $field->getVariableName();
            }
        }
        foreach ($form->getOutputs() as $output) {
            $names[] = $output->getVariableName();
        }

        return $names;
    }

    /**
     * Checked at save time, never at render time - an admin gets the error on the screen where the formula was typed.
     *
     * @param list<string> $variableNames
     *
     * @return string|null the translated reason it can't be evaluated, or null when it can
     */
    public function lint(?string $expression, array $variableNames): ?string
    {
        $expression = trim((string) $expression);
        if ('' === $expression) {
            return $this->translator->trans('text.expression_empty', [], 'ui');
        }

        // A French keyboard writes 1,15 - which the parser reads as two arguments, and which no error message would otherwise explain. Only flagged with no space after it, "min(1, 2)" being a legitimate two-argument call
        if (preg_match('/\d,\d/', $expression)) {
            return $this->translator->trans('text.expression_decimal_comma', [], 'ui');
        }

        if (!preg_match(self::ALLOWED_CHARACTERS, $expression)) {
            return $this->translator->trans('text.expression_forbidden_characters', [], 'ui');
        }

        // Counted here rather than left to the parser: an unclosed bracket is what a long formula actually gets wrong, and saying which way it is unbalanced beats naming the character position it was noticed at
        if (null !== $parenthesis = $this->unbalancedParenthesis($expression)) {
            return $parenthesis;
        }

        // Checked before the parser, which finds the same mistake and reports it in a language this bundle does not choose: the admin gets the name they mistyped, and the closest one that exists, in the language they are working in
        if (null !== $variable = $this->unknownVariable($expression, $variableNames)) {
            return $variable;
        }

        try {
            $this->expressionLanguage->lint($expression, $variableNames);
        } catch (SyntaxError $e) {
            // Whatever the three checks above did not catch - an operator in the wrong place, a function called with too few arguments. Led in the admin's own language, the parser's own wording kept behind it rather than dropped: it names the position, which nothing here can
            return $this->translator->trans('text.expression_not_understood', ['%error%' => $e->getMessage()], 'ui');
        }

        return null;
    }

    // Which way the brackets fail to match, or null when they do
    private function unbalancedParenthesis(string $expression): ?string
    {
        $open = substr_count($expression, '(');
        $close = substr_count($expression, ')');

        if ($open === $close) {
            return null;
        }

        return $this->translator->trans(
            $open > $close ? 'text.expression_unclosed_parenthesis' : 'text.expression_unopened_parenthesis',
            ['%missing%' => abs($open - $close)],
            'ui'
        );
    }

    /** @param list<string> $variableNames */
    private function unknownVariable(string $expression, array $variableNames): ?string
    {
        // A name followed by "(" is a function call and not a variable - the possessive quantifier is what stops "round(" from matching as the variable "roun", the parser's own six names being its business anyway
        preg_match_all('/[A-Za-z_][A-Za-z0-9_]*+(?!\s*\()/', $expression, $matches);

        foreach ($matches[0] as $name) {
            if (in_array($name, $variableNames, true)) {
                continue;
            }

            $closest = $this->closestName($name, $variableNames);

            return null === $closest
                ? $this->translator->trans('text.expression_unknown_variable', ['%variable%' => $name], 'ui')
                : $this->translator->trans('text.expression_unknown_variable_suggestion', ['%variable%' => $name, '%suggestion%' => $closest], 'ui');
        }

        return null;
    }

    /**
     * The name the admin most likely meant, or null when none is close enough to be worth naming.
     *
     * @param list<string> $variableNames
     */
    private function closestName(string $name, array $variableNames): ?string
    {
        // A third of the name's own length, so a long variable tolerates the handful of characters a short one must not: "budget_e85" suggested for "budget_e58", nothing suggested for "toto"
        $tolerance = max(2, (int) (mb_strlen($name) / 3));
        $closest = null;

        foreach ($variableNames as $candidate) {
            $distance = levenshtein($name, $candidate);
            if ($distance <= $tolerance) {
                $tolerance = $distance;
                $closest = $candidate;
            }
        }

        return $closest;
    }

    /**
     * Runs the whole calculator once.
     *
     * @param array<string, mixed> $inputs raw submitted values keyed by FormField::getName()
     *
     * @return array<string, array{value: float|null, formatted: string|null}> keyed by FormOutput::getName()
     */
    public function compute(Form $form, array $inputs): array
    {
        $variables = [];
        foreach ($form->getFields() as $field) {
            if ($field->isNumeric()) {
                $variables[$field->getVariableName()] = $this->numeric($field, $inputs[(string) $field->getName()] ?? null);
            }
        }

        $results = [];
        foreach ($form->getOutputs() as $output) {
            $value = $this->evaluate($output, $variables);
            // An output that failed is still declared, so the ones after it fail on an unknown variable rather than on a missing key - and 0.0 keeps a chained division from turning one broken formula into a cascade of them
            $variables[$output->getVariableName()] = $value ?? 0.0;

            $results[(string) $output->getName()] = [
                'value' => $value,
                'formatted' => null === $value ? null : $this->format($output, $value),
            ];
        }

        return $results;
    }

    // A single formula. Anything it throws is the visitor's problem, not a 500: a slider left at zero makes a division by zero, an admin's half-typed formula a SyntaxError, and both simply mean "no result yet"
    private function evaluate(FormOutput $output, array $variables): ?float
    {
        try {
            $value = $this->expressionLanguage->evaluate((string) $output->getExpression(), $variables);
        } catch (\Throwable) {
            return null;
        }

        return is_numeric($value) && is_finite((float) $value) ? (float) $value : null;
    }

    // The field's own value, its default when the visitor hasn't touched it, then the value the control itself shows when neither is set - a calculator always has a number to work with, and one that matches what is on screen: a range with no default sits at the middle of its span and a choice on its first option, so falling straight back to 0 printed a result contradicting the controls until the first keystroke, and forever without JavaScript
    private function numeric(FormField $field, mixed $submitted): float
    {
        foreach ([$submitted, $field->getDefaultValue(), $this->initial($field)] as $candidate) {
            if (is_numeric($candidate)) {
                return (float) $candidate;
            }
        }

        return 0.0;
    }

    // What the browser displays for a field left untouched: the middle of a range's span (its own default when min/max aren't set), and the first option of a choice, which carries no placeholder (see FormSubmissionType)
    private function initial(FormField $field): mixed
    {
        return match ($field->getType()) {
            FormField::TYPE_RANGE => (($field->getMinValue() ?? 0.0) + ($field->getMaxValue() ?? 100.0)) / 2,
            FormField::TYPE_CHOICE => $field->getOptions()[0]['value'] ?? null,
            default => null,
        };
    }

    // Formatted server-side and handed to the browser ready to print, so the same formula shows the same number whether the page was rendered or refreshed by fetch
    private function format(FormOutput $output, float $value): string
    {
        $formatter = new \NumberFormatter(
            $this->translator->getLocale(),
            FormOutput::FORMAT_CURRENCY === $output->getFormat() ? \NumberFormatter::CURRENCY : \NumberFormatter::DECIMAL
        );
        $formatter->setAttribute(\NumberFormatter::FRACTION_DIGITS, $output->getDecimals());

        if (FormOutput::FORMAT_CURRENCY === $output->getFormat()) {
            // The currency the locale itself names when it names one - a plain "fr" (no region, which is what a c975L site runs on) answers "XXX", the code for "no currency", and would print an "¤" placeholder. Euro is the fallback rather than a site-wide setting nobody would change; a calculator quoting anything else states it as a plain number with its own unit
            $currency = $formatter->getTextAttribute(\NumberFormatter::CURRENCY_CODE);
            $formatted = $formatter->formatCurrency($value, in_array($currency, ['', 'XXX', false], true) ? 'EUR' : $currency);
        } else {
            $formatted = $formatter->format($value);
            if (FormOutput::FORMAT_PERCENT === $output->getFormat()) {
                $formatted .= "\u{00A0}%";
            }
        }

        // Set off by a non-breaking space, as the percent above is and as the currency formatter is before its own symbol: an admin types "t", never " t", the field carrying it being trimmed on the way in
        $unit = trim((string) $output->getUnit());

        return ($formatted ?: (string) $value) . ('' === $unit ? '' : "\u{00A0}" . $unit);
    }
}
