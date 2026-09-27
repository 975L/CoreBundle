<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
use Symfony\Component\Validator\Constraints\Regex;

// The one rule a new password answers to, wherever it is chosen (registration, reset, account page): 8 characters at least with a lowercase letter, an uppercase letter, a digit and a sign, said in one message rather than PasswordStrength's entropy score nobody can aim for, and no password a breach made public
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class PasswordPolicy extends Compound
{
    public const string PATTERN = '/^(?=.*\p{Ll})(?=.*\p{Lu})(?=.*\p{N})(?=.*[^\p{L}\p{N}]).{8,}$/u';

    // Checked in this order, the rule itself first
    #[\Override]
    protected function getConstraints(array $options): array
    {
        return [
            new Regex(pattern: self::PATTERN, message: 'text.password_complexity'),
            new Length(max: 25, maxMessage: 'text.password_max_length'),
            new NotCompromisedPassword(),
        ];
    }
}
