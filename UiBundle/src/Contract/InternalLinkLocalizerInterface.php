<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

// The same link, read in the language the page around it is being read in: a card, a call to action or a word in a rich text pointing at "/pages/nos-ateliers" sends a visitor reading the site in English to "/en/pages/nos-ateliers", and never back into the language they left. Implemented by the bundle owning the pages - only it knows which urls are its own, which of them a language really answers for, and what the localised route is called; with none registered every link is left exactly as it was stored
interface InternalLinkLocalizerInterface
{
    // The value as the current language reads it - a bare path, or a whole rich text whose own links are rewritten - given back unchanged for anything the implementation does not recognise: an external url, an anchor, a mailto, a page saying nothing in this language, and every request served in the writing language
    public function localize(string $value): string;
}
