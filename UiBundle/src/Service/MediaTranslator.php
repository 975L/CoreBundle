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
use Symfony\Component\HttpFoundation\File\File;

// What a media says in another language: the caption, the title and the text of a portfolio card, the alternative a screen reader announces, and the file itself when the picture carries words (a screenshot, a banner). Link, credits and dimensions stay one value for every language. The texts live on the row rather than in Block::$data, out of ContentTranslator's reach through the block (see BlockExtension, which lays them on before a render).
class MediaTranslator
{
    // The vocabulary this bundle's rows are named with, the way Block and FormField name theirs
    public const string OWNER = Translation::OWNER_MEDIA;

    // What a translation may cover of a media itself: what is read under it, and what is read instead of it
    public const array FIELDS = ['label', 'description', 'alt'];

    // The file a language shows instead of the media's own, stored as a translation like the texts but never offered as one: its value is a path, not a sentence (see stageFile)
    public const string FILE = 'filename';

    // The files a form has just been given, written by MediaTranslationFileListener once the flush that saves their block goes through, the same wait the texts get (see ContentTranslator::stage)
    /** @var list<array{0: File|null, 1: string|null, 2: string|null, 3: int, 4: string}> file, target, previous, media id, locale */
    private array $pendingFiles = [];

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
            $media->setTranslated($this->contentTranslator->translate(self::OWNER, $id, [], [...self::FIELDS, self::FILE], $locale));
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

    // Whether a path read back from a row or an archive stays under public/, the only place a media's file ever lives
    public static function isPublicPath(mixed $path): bool
    {
        return is_string($path) && '' !== $path && !str_contains($path, '..') && !str_starts_with($path, '/');
    }

    // Whether a path read back from a row or an archive is named the way stageFile names a language's file, an image after the media's own when $mediaFilename is known: anything else ("index.php") is never written nor removed
    public static function isTranslatedFilePath(mixed $path, ?string $mediaFilename = null): bool
    {
        if (!self::isPublicPath($path) || 1 !== preg_match('/^(.+)-[a-z]{2}(?:_[A-Z]{2})?-[0-9a-f]{8}\.(?:avif|bmp|gif|ico|jpe?g|png|svg|tiff?|webp)$/', basename($path), $matches)) {
            return false;
        }

        return null === $mediaFilename || $matches[1] === pathinfo($mediaFilename, \PATHINFO_FILENAME);
    }

    // The file a language already shows instead of the media's own, for the screen that offers to take it back
    public function translatedFile(Media $media, string $locale): ?string
    {
        $id = $media->getId();

        return null === $id ? null : $this->contentTranslator->values(self::OWNER, $id, $locale)[self::FILE] ?? null;
    }

    // Puts a file of its own on a media for one language, or takes it back with $remove: named after the media's own file with the language and a hash of its content added ("block-hero-12-ab-en-1a2b3c4d.webp"), beside it, so it is served from where the original is and a replaced file gets a new URL
    public function stageFile(Media $media, string $locale, ?File $file, bool $remove = false): void
    {
        $id = $media->getId();
        $source = $media->getUntranslated(self::FILE);
        if (null === $id || null === $source || (null === $file && !$remove)) {
            return;
        }

        $previous = $this->translatedFile($media, $locale);

        $target = null;
        if (null !== $file) {
            $directory = pathinfo($source, \PATHINFO_DIRNAME);
            $extension = $file->guessExtension() ?? pathinfo($source, \PATHINFO_EXTENSION);
            $target = ('.' === $directory ? '' : $directory . '/') . pathinfo($source, \PATHINFO_FILENAME) . '-' . $locale . '-' . substr((string) md5_file($file->getPathname()), 0, 8) . '.' . $extension;
        }

        $this->contentTranslator->stage(self::OWNER, $id, $locale, [self::FILE => $target]);
        $this->pendingFiles[] = [$file, $target, $previous, $id, $locale];
    }

    // What is waiting to be written, emptied on the way out so a second flush writes nothing twice
    /** @return list<array{0: File|null, 1: string|null, 2: string|null, 3: int, 4: string}> */
    public function takePendingFiles(): array
    {
        $pending = $this->pendingFiles;
        $this->pendingFiles = [];

        return $pending;
    }
}
