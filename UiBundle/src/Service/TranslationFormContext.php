<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

// The language the screen being rendered writes in, for what cannot be handed the form's own options: a form theme block is given "form" and "attr" and nothing else, too far below the sub-form BlockType puts in translation mode to walk up to
// Per request by definition, a request rendering one screen and a screen writing one language - null on every screen of every single-language site
class TranslationFormContext
{
    private ?string $locale = null;

    public function set(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function get(): ?string
    {
        return $this->locale;
    }
}
