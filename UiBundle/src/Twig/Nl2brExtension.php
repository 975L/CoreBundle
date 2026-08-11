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

class Nl2brExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            // Overrides Twig's own 'nl2br' filter, for an HTML5 <br> instead of its <br /> - 'pre_escape' is the native one's, without which {{ value|nl2br }} would come out unescaped ({{ value|raw|nl2br }} always goes through)
            new TwigFilter('nl2br', [self::class, 'nl2br'], ['pre_escape' => 'html', 'is_safe' => ['html']]),
        ];
    }

    public static function nl2br($string): string
    {
        return nl2br($string ?? '', false);
    }
}
