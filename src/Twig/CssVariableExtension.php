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
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class CssVariableExtension extends AbstractExtension
{
    public function __construct(
        private CssVariableResolver $resolver,
    ) {
    }

    public function getFilters(): array
    {
        return [
            // Applied to the whole <style> of an email layout, before inline_css: the CSS inliner copies
            // declarations verbatim into style="" attributes, and no mail client resolves a custom property
            new TwigFilter('resolve_css_variables', [$this, 'resolve'], ['is_safe' => ['html']]),
        ];
    }

    public function resolve(string $css): string
    {
        return $this->resolver->resolve($css);
    }
}
