<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

// The expression language a FormOutput is written in - deliberately smaller than Symfony's own: registerFunctions() is overridden WITHOUT calling the parent, which is what drops the built-in "constant()" and "enum()", the only two functions able to reach outside the values handed to evaluate(). What remains is six arithmetic helpers, and variables that ExpressionEvaluator guarantees are floats, so nothing an admin can type has an object to call a method on
class CalculatorExpressionLanguage extends ExpressionLanguage
{
    public const FUNCTIONS = ['abs', 'ceil', 'floor', 'max', 'min', 'round'];

    #[\Override]
    protected function registerFunctions(): void
    {
        foreach (self::FUNCTIONS as $function) {
            $this->addFunction(ExpressionFunction::fromPhp($function));
        }
    }
}
