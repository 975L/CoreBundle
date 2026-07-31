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

// Class-level constraint on Media - only the roles carrying a fixed icon spec (favicon, apple-touch-icon) are concerned, and what they accept depends on what the server can rasterize, checked by FixedIconFormatValidator
#[\Attribute(\Attribute::TARGET_CLASS)]
class FixedIconFormat extends Constraint
{
    public string $message = 'label.fixed_icon_invalid_format';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
