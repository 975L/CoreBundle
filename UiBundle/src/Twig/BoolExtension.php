<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class BoolExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('to_bool', [$this, 'toBool']),
        ];
    }

    public function toBool(mixed $value): bool
    {
        return !\in_array($value, [false, 'false', '0', 0, ''], true);
    }
}
