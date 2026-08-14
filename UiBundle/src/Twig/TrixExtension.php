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

class TrixExtension
{
    // Reduces editor HTML to the text a caption or a meta tag can hold - the entities are decoded, else Twig would escape them a second time
    #[AsTwigFilter('plain_text')]
    public function plainText(?string $html): string
    {
        if (!$html) {
            return '';
        }

        // Only a line break stands for a space, an inline tag closing mid-sentence would otherwise push one in front of the punctuation that follows
        $text = (string) preg_replace('#<(?:br|/?(?:p|div|li|ul|ol|h[1-6]|blockquote|table|tr|td|th))\b[^>]*>#i', ' ', $html);
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    // Drops Trix's block-level <div> wrappers, invalid where only phrasing content is allowed, joining lines with <br>
    #[AsTwigFilter('trix_inline', isSafe: ['html'])]
    public function trixInline(?string $html): string
    {
        if (!$html) {
            return '';
        }

        $html = (string) preg_replace('#</div>\s*<div[^>]*>#', '<br>', trim($html));

        return (string) preg_replace('#^<div[^>]*>|</div>$#', '', $html);
    }
}
