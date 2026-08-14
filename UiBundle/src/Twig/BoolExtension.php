<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Twig\Attribute\AsTwigFilter;

class BoolExtension
{
    #[AsTwigFilter('to_bool')]
    public function toBool(mixed $value): bool
    {
        return !\in_array($value, [false, 'false', '0', 0, ''], true);
    }
}
