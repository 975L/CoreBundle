<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator;

use Symfony\Component\Validator\Constraint;

// Class-level constraint on Form: every FormOutput expression it owns has to be evaluable against that same Form's own variables, which no single output can check on its own - it depends on the fields beside it and on the outputs placed before it. Checked by ValidExpressionsValidator
#[\Attribute(\Attribute::TARGET_CLASS)]
class ValidExpressions extends Constraint
{
    #[\Override]
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
