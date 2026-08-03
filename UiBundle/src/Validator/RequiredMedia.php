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

// Class-level constraint on Block - whether it actually applies depends on BlockRegistry::isMediaRequired() for the block's own kind, checked by RequiredMediaValidator
#[\Attribute(\Attribute::TARGET_CLASS)]
class RequiredMedia extends Constraint
{
    public string $message = 'label.block_media_required';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
