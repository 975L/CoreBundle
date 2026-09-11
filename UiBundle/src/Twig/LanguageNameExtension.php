<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Twig;

use Symfony\Component\Intl\Locales;
use Twig\Attribute\AsTwigFilter;

// A language named in its own words, the way a menu entry or a tab names it rather than the way a sentence does. Twig's own "locale_name" gives what Intl holds - "français", "español", lowercase, because that is how the word reads inside a French or a Spanish sentence; standing alone as a label it reads as a mistake
class LanguageNameExtension
{
    // Only the first letter is touched: "English" is already capitalised, and a language whose script has no case is given back exactly as it came
    #[AsTwigFilter('language_name')]
    public function languageName(string $locale): string
    {
        return mb_ucfirst(Locales::getName($locale, $locale));
    }
}
