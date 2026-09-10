<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;

// What a media says in another language: the caption a visitor reads, the title and the text of a portfolio card, and the alternative a screen reader announces.
// A media is the same file in every language and its texts are not - which is why a language screen offers these three and nothing else: replacing the file, its link target, its credits or its dimensions per language would be offered here and read nowhere.
// The three texts live on the row rather than in Block::$data, so ContentTranslator cannot reach them through the block that hangs them (see BlockExtension, which lays them on just before a render).
class MediaTranslator
{
    // The vocabulary this bundle's rows are named with, the way Block and FormField name theirs
    public const string OWNER = Translation::OWNER_MEDIA;

    // What a translation may cover of a media itself: what is read under it, and what is read instead of it
    public const array FIELDS = ['label', 'description', 'alt'];

    public function __construct(private readonly ContentTranslator $contentTranslator)
    {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    // Lays the language being rendered over each media's own texts, for the render being built and no longer than that (see Media::setTranslated, whose overlay Doctrine never persists)
    /** @param iterable<Media> $medias */
    public function apply(iterable $medias, ?string $locale = null): void
    {
        // Tested before the collection is touched: on a single-language site the proxy behind it is never initialised, and a page of blocks costs no query at all here
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        $medias = $medias instanceof \Traversable ? iterator_to_array($medias) : $medias;

        if ([] === $medias) {
            return;
        }

        $this->preload($medias, $locale);

        foreach ($medias as $media) {
            $id = $media->getId();
            if (null === $id) {
                continue;
            }

            // Given nothing to lay over, translate() hands back the translated fields alone - an untranslated one is absent rather than null, which is what makes the getters fall back on the text the media was written in
            $media->setTranslated($this->contentTranslator->translate(self::OWNER, $id, [], self::FIELDS, $locale));
        }
    }

    // Reads ahead a whole set of medias, so a grid of a dozen cards costs one query rather than a dozen
    /** @param iterable<Media> $medias */
    public function preload(iterable $medias, ?string $locale = null): void
    {
        if (!$this->contentTranslator->isActive()) {
            return;
        }

        $ids = [];
        foreach ($medias as $media) {
            $id = $media->getId();
            if (null !== $id) {
                $ids[] = $id;
            }
        }

        if ([] !== $ids) {
            $this->contentTranslator->preload(self::OWNER, $ids, $locale);
        }
    }

    // Every language a media has been given, for the screen that writes them
    /** @return array<string, array<string, string|null>> locale => field => value */
    public function all(Media $media): array
    {
        $id = $media->getId();

        return null === $id ? [] : $this->contentTranslator->all(self::OWNER, $id);
    }

    // What a language screen offers for each of a media's texts: what that language already says, or the source text between brackets where it says nothing yet
    /** @return array<string, string|null> field => value */
    public function promptValues(Media $media, string $locale): array
    {
        $id = $media->getId();
        $written = null === $id ? [] : $this->contentTranslator->values(self::OWNER, $id, $locale);

        $values = [];
        foreach (self::FIELDS as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($media->getUntranslated($field));
        }

        return $values;
    }

    // Hands what a language screen wrote over to be stored on the flush that saves the block, a field left holding the bracketed source counting as nothing written (see ContentTranslator::stage)
    /** @param array<string, string|null> $values field => value */
    public function stage(Media $media, string $locale, array $values): void
    {
        $id = $media->getId();
        if (null === $id) {
            return;
        }

        $staged = [];
        foreach (self::FIELDS as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $source = $media->getUntranslated($field);
            $staged[$field] = ContentTranslator::untouched($values[$field], $source) ? null : $values[$field];
        }

        if ([] !== $staged) {
            $this->contentTranslator->stage(self::OWNER, $id, $locale, $staged);
        }
    }
}
