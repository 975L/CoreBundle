<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class Captcha extends Constraint
{
    // Same message either way on purpose: a visitor can do nothing about a missing token (JavaScript off, script blocked) any more than about a low score, and telling a bot which of the two it tripped is free information
    public string $message = 'text.captcha_failed';
}
