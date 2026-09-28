<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

// Implement to hand "c975l:translate:content" the texts a bundle has the site say again in another language - a page, its blocks, a menu, a form - each named by the owner and field ContentTranslator stores it under. Only what the site itself says belongs here: a book or a product is written in its own language and keeps it
#[AutoconfigureTag('c975l.translatable_text_provider')]
interface TranslatableTextProviderInterface
{
    // Every text holding words in the writing language, a field left empty having nothing behind it to translate
    /** @return iterable<array{owner: string, ownerId: int, field: string, source: string, label: string}> */
    public function getTranslatableTexts(): iterable;
}
