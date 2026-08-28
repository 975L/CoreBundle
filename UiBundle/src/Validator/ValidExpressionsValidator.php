<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Service\ExpressionEvaluator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

// Runs on every save of a Form, not only when an expression was edited: FormFieldNamer re-derives each name from its label, so merely relabelling "Prix de l'E85" renames the variable the formulas read - which has to surface here as an error on the formula that lost its variable, rather than as a calculator quietly showing dashes
class ValidExpressionsValidator extends ConstraintValidator
{
    public function __construct(private readonly ExpressionEvaluator $expressionEvaluator)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidExpressions) {
            throw new UnexpectedTypeException($constraint, ValidExpressions::class);
        }

        if (!$value instanceof Form || !$value->isCalculator()) {
            return;
        }

        // An expression only ever sees what is declared before it, so the list grows as the outputs are walked - which is also what stops a formula from referencing itself, or two of them from waiting on each other
        $variableNames = [];
        foreach ($value->getFields() as $field) {
            if ($field->isNumeric()) {
                $variableNames[] = $field->getVariableName();
            }
        }

        foreach ($value->getOutputs() as $output) {
            $error = $this->expressionEvaluator->lint($output->getExpression(), $variableNames);
            if (null !== $error) {
                // On the collection rather than on a row: removeElement() leaves holes in the keys, so a counter would point the message at the wrong output once a row has been deleted - and the message names the faulty output itself
                $this->context
                    ->buildViolation('label.expression_invalid')
                    ->setParameter('%output%', (string) $output->getLabel())
                    ->setParameter('%error%', $error)
                    ->atPath('outputs')
                    ->addViolation();
            }

            $variableNames[] = $output->getVariableName();
        }
    }
}
