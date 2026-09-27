<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Validator\Constraints;

use c975L\UiBundle\Validator\Constraints\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraints\NotCompromisedPasswordValidator;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

class PasswordPolicyTest extends TestCase
{
    // Each password with the messages it earns, the breach lookup switched off
    #[DataProvider('passwords')]
    public function testThePolicyIsTheOneSaid(string $password, array $expected): void
    {
        $validator = Validation::createValidatorBuilder()
            ->setConstraintValidatorFactory(new ConstraintValidatorFactory([NotCompromisedPasswordValidator::class => new NotCompromisedPasswordValidator(null, 'UTF-8', false)]))
            ->getValidator();

        $messages = array_map(static fn ($violation): string => $violation->getMessageTemplate(), iterator_to_array($validator->validate($password, new PasswordPolicy())));

        $this->assertSame($expected, $messages);
    }

    // A lowercase, an uppercase, a digit, a sign, 8 characters at least and 25 at most
    public static function passwords(): iterable
    {
        yield 'all four, 8 long' => ['Abcdef1!', []];
        yield 'accented letters count' => ['Éléphant9?', []];
        yield 'too short' => ['Ab1!', ['text.password_complexity']];
        yield 'no sign' => ['Abcdefg1', ['text.password_complexity']];
        yield 'no digit' => ['Abcdefg!', ['text.password_complexity']];
        yield 'no uppercase' => ['abcdef1!', ['text.password_complexity']];
        yield 'no lowercase' => ['ABCDEF1!', ['text.password_complexity']];
        yield 'too long' => ['Abcdef1!' . str_repeat('x', 20), ['text.password_max_length']];
    }
}
