<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator\Constraints;

use c975L\UiBundle\Service\CaptchaVerifier;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

// Checks the token CaptchaType's field carries - see CaptchaVerifier
class CaptchaValidator extends ConstraintValidator
{
    public function __construct(private readonly CaptchaVerifier $captchaVerifier)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof Captcha) {
            throw new UnexpectedTypeException($constraint, Captcha::class);
        }

        // No keys configured: the widget rendered nothing to verify, so there's nothing to hold against the visitor
        if (!$this->captchaVerifier->isEnabled()) {
            return;
        }

        if (!$this->captchaVerifier->verify(null === $value ? null : (string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
