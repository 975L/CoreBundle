<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

// What a page says in a language other than the one it was written in - a service rather than a trait shared by two packages, which ECOSYSTEM.md §24 warns against
// The default language is never stored, this only ever laying something over it: a site declaring one language has isActive() false, and every call below returns what it was given
class ContentTranslator
{
    // What has already been read this request, per owner type and language: a page walks its blocks one by one, and each would otherwise cost a query of its own
    private array $loaded = [];

    // What a form has handed over and no flush has written yet (see stage())
    private array $pending = [];

    public function __construct(
        private readonly TranslationRepository $repository,
        private readonly EntityManagerInterface $manager,
        private readonly RequestStack $requestStack,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    // Nothing to translate on a site offering a single language, which is the short-circuit the whole design rests on
    public function isActive(): bool
    {
        return $this->siteLocales->isMultilingual();
    }

    /**
     * The languages a translation may be written in: every declared one but the default, which is the text itself.
     *
     * @return list<string>
     */
    public function getTranslatableLocales(): array
    {
        return $this->siteLocales->translatable();
    }

    /**
     * Reads ahead the translations of a whole set of owners, so a page costs one query rather than one per block.
     *
     * @param list<int> $ownerIds
     */
    public function preload(string $ownerType, array $ownerIds, ?string $locale = null): void
    {
        $locale ??= $this->currentLocale();
        if (!$this->translates($locale) || [] === $ownerIds) {
            return;
        }

        $known = $this->loaded[$ownerType][$locale] ?? [];
        $missing = array_values(array_diff($ownerIds, array_keys($known)));
        if ([] === $missing) {
            return;
        }

        // Ids holding no translation are remembered empty, or each would be asked of the database again for every block rendered
        $found = $this->repository->findValues($ownerType, $missing, $locale);
        foreach ($missing as $ownerId) {
            $known[$ownerId] = $found[$ownerId] ?? [];
        }

        $this->loaded[$ownerType][$locale] = $known;
    }

    /**
     * Reads ahead the translations of everything a page is about to render: its blocks, and the slots its containers
     * hold. One query for the page, where each block asking for itself would run one apiece.
     *
     * @param iterable<Block> $blocks
     */
    public function preloadBlocks(iterable $blocks, ?string $locale = null): void
    {
        if (!$this->translates($locale ?? $this->currentLocale())) {
            return;
        }

        $ids = [];
        $this->collectBlockIds($blocks, $ids);

        $this->preload(Translation::OWNER_BLOCK, array_values($ids), $locale);
    }

    /**
     * @param iterable<Block> $blocks
     * @param array<int, int> $ids
     */
    private function collectBlockIds(iterable $blocks, array &$ids): void
    {
        foreach ($blocks as $block) {
            $id = $block->getId();

            // Kept rather than followed blindly: a container and one of its slots pointing at each other would spin here for good, as BlockCacheInvalidationListener already notes
            if (null === $id || isset($ids[$id])) {
                continue;
            }

            $ids[$id] = $id;
            $this->collectBlockIds($block->getSlots(), $ids);
        }
    }

    /**
     * The values given, with whatever this owner has been translated to laid over them.
     *
     * A merge, not a resolution: a field nobody translated keeps the text it was written in, so a half-translated
     * page reads in two languages rather than showing holes - and the templates never hear about any of this.
     *
     * @param array<string, mixed> $values
     * @param list<string>         $fields the keys a translation may cover, an empty list meaning none
     *
     * @return array<string, mixed>
     */
    public function translate(string $ownerType, ?int $ownerId, array $values, array $fields, ?string $locale = null): array
    {
        $locale ??= $this->currentLocale();

        if (null === $ownerId || [] === $fields || !$this->translates($locale)) {
            return $values;
        }

        $this->preload($ownerType, [$ownerId], $locale);

        foreach ($this->loaded[$ownerType][$locale][$ownerId] ?? [] as $field => $value) {
            // An empty translation never overwrites the original text, being an entry opened then left blank
            if (\in_array($field, $fields, true) && null !== $value && '' !== $value) {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    /**
     * Writes what the "Translate" screen was given: one row per field, the empty ones taken away rather than stored.
     *
     * @param array<string, string|null> $values field => value
     */
    public function store(string $ownerType, int $ownerId, string $locale, array $values): void
    {
        if (!$this->translates($locale)) {
            return;
        }

        foreach ($values as $field => $value) {
            $translation = $this->repository->findOneField($ownerType, $ownerId, $field, $locale);
            $value = null === $value || '' === trim($value) ? null : $value;

            if (null === $value) {
                if (null !== $translation) {
                    $this->manager->remove($translation);
                }

                continue;
            }

            $translation ??= new Translation($ownerType, $ownerId, $field, $locale);
            $translation->setValue($value);
            $this->manager->persist($translation);
        }

        $this->manager->flush();

        // What has just been written has to show: the locale is already in the block cache key, but that language's entry keeps being served until something invalidates it
        unset($this->loaded[$ownerType][$locale][$ownerId]);
    }

    // What a language screen offers in a field nobody has written yet: the source text between brackets, both the thing to translate and the mark of what is left to do
    public static function prompt(mixed $source): ?string
    {
        return is_string($source) && '' !== $source ? '[' . $source . ']' : null;
    }

    // Whether what came back is still the prompt above rather than a translation, compared on the words alone: a rich text editor re-serialises what it was given
    public static function untouched(mixed $value, mixed $source): bool
    {
        $prompt = self::prompt($source);
        if (!is_string($value) || null === $prompt) {
            return false;
        }

        $plain = static fn (string $text): string => trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5)));

        return $plain($value) === $plain($prompt);
    }

    /**
     * What a form has just been given, kept until the flush that saves its owner goes through.
     *
     * A form cannot write these itself: its POST_SUBMIT fires before the root form is validated, so a store() there
     * would persist a submission that is about to be refused. TranslationWriteListener drains this on postFlush -
     * the flush being the owner's own save, which a failed validation never reaches.
     *
     * @param array<string, string|null> $values field => value
     */
    public function stage(string $ownerType, int $ownerId, string $locale, array $values): void
    {
        $this->pending[] = [$ownerType, $ownerId, $locale, $values];
    }

    /**
     * What is waiting to be written, emptied on the way out so a second flush writes nothing twice.
     *
     * @return list<array{0: string, 1: int, 2: string, 3: array<string, string|null>}>
     */
    public function takePending(): array
    {
        $pending = $this->pending;
        $this->pending = [];

        return $pending;
    }

    /**
     * Every language this owner has been given, for the screen that writes them.
     *
     * @return array<string, array<string, string|null>> locale => field => value
     */
    public function all(string $ownerType, int $ownerId): array
    {
        return $this->isActive() ? $this->repository->findByOwner($ownerType, $ownerId) : [];
    }

    /**
     * What one owner says in one language, read through the same cache the rendering fills.
     *
     * A language screen asks this once per block: going through preload() above, the whole tree costs the one query
     * its root already ran rather than one apiece - and nothing at all on the way back, the cache still holding it.
     *
     * @return array<string, string|null> field => value
     */
    public function values(string $ownerType, int $ownerId, string $locale): array
    {
        $this->preload($ownerType, [$ownerId], $locale);

        return $this->loaded[$ownerType][$locale][$ownerId] ?? [];
    }

    // A language other than the one the text was written in, on a site declaring several: everything else is already stored
    private function translates(string $locale): bool
    {
        return \in_array($locale, $this->getTranslatableLocales(), true);
    }

    private function currentLocale(): string
    {
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->siteLocales->getDefaultLocale();
    }
}
