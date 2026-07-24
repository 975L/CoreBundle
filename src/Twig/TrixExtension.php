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

class TrixExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('trix_inline', [$this, 'trixInline'], ['is_safe' => ['html']]),
        ];
    }

    // Trix always wraps every line in its own block-level <div> (never <p>, even for a single-line
    // field) - invalid wherever only phrasing content is allowed (e.g. inside <h1>, see Hero/Hero.html.twig).
    // Adjacent blocks are joined with <br> so multi-line input still breaks visually, then the wrapping
    // tags themselves are dropped.
    public function trixInline(?string $html): string
    {
        if (!$html) {
            return '';
        }

        $html = (string) preg_replace('#</div>\s*<div[^>]*>#', '<br>', trim($html));

        return (string) preg_replace('#^<div[^>]*>|</div>$#', '', $html);
    }
}
