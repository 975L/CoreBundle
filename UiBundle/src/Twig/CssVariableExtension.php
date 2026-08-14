<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use c975L\UiBundle\Service\CssVariableResolver;
use Twig\Attribute\AsTwigFilter;

class CssVariableExtension
{
    public function __construct(
        private readonly CssVariableResolver $resolver,
    ) {
    }

    #[AsTwigFilter('resolve_css_variables', isSafe: ['html'])]
    public function resolve(string $css): string
    {
        return $this->resolver->resolve($css);
    }
}
