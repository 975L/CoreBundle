<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Management\PaginatorPageSize;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Hands management/paginator.html.twig the sizes it offers, so the list an admin clicks and the list PaginatorPageSize accepts stay the same one
class PageSizeExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('page_sizes', [$this, 'getSizes']),
        ];
    }

    /** @return int[] */
    public function getSizes(): array
    {
        return PaginatorPageSize::SIZES;
    }
}
