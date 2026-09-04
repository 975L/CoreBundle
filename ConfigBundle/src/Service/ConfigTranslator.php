<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Service;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\UiBundle\Service\ContentTranslator;

// What a setting says in a language other than the one it was typed in - the same layer a page's title is translated through (see ECOSYSTEM.md §26), applied to the one text a site writes once for the whole of it
// The typed value is never moved: it stays in Config::$value and plays the part of the msgid, exactly as Page::$title does. A site declaring a single language never reaches any of this
class ConfigTranslator
{
    // The name this bundle stores its translations under, as SiteBundle names its pages "site_page": Ui holds the table and knows nothing of a Config
    public const string OWNER = 'site_config';

    // The one field of a setting a language can change - its value. Everything else about it (its slug, its group, its kind) is the same in every language
    public const string FIELD = 'value';

    // The kinds a sentence can be written in: a boolean, a number, a date, a font or a value picked from a fixed list says the same thing in every language, and a json holds a structure rather than words
    public const array KINDS = [Config::TYPE_TEXT, Config::TYPE_TEXTAREA, Config::TYPE_HTML];

    // The settings a language may actually be written for, named one by one. Holding words is not enough: almost every text setting of a site is a url, a key, a postal address, a name or a technical directive, said the same way in every language
    // A setting only belongs here when a template renders it through config(slug, locale), the one read that resolves the layer (see ConfigExtension::getConfig()): the legal identity, the SEO files and the confirmation e-mail all read ConfigService::get() straight and would never see the translation, so offering it there would store text nothing renders
    // A bundle shipping a setting that is genuinely written in words names its slug here
    public const array TRANSLATABLE = ['site-age-warning'];

    // The slug => id map, read once for the request: the values are handed around by slug (see ConfigService), and the translation table names its owner by id
    private ?array $ids = null;

    public function __construct(
        private readonly ContentTranslator $contentTranslator,
        private readonly ConfigRepository $configRepository,
    ) {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator->isActive();
    }

    /**
     * @return list<string>
     */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator->getTranslatableLocales();
    }

    // Whether a language may be written for this setting at all: a site offering one language, a setting nothing renders per-locale, a kind holding no words, and there is nothing to translate
    // A sensitive setting is left out whatever its slug: a secret holds no sentence, and the language screen would hand its encrypted envelope back to updateEntity() to be encrypted a second time
    public function translates(Config $config): bool
    {
        return $this->isActive()
            && true !== $config->getIsSensitive()
            && \in_array((string) $config->getSlug(), self::TRANSLATABLE, true)
            && \in_array((string) $config->getKind(), self::KINDS, true);
    }

    // The value a setting has in the given language, or the one it was typed in where that language says nothing
    // A merge and not a resolution, like every other read of the layer: a setting nobody translated keeps its own text, so nothing ever renders empty because a language was left aside
    public function value(string $slug, mixed $value, ?string $locale = null): mixed
    {
        // Everything the layer never covers is handed straight back, the id below being a query this must not cost on a site with one language
        if (!\is_string($value) || '' === $value || !$this->isActive()) {
            return $value;
        }

        $id = $this->idFor($slug, $locale);
        if (null === $id) {
            return $value;
        }

        return $this->contentTranslator->translate(self::OWNER, $id, [self::FIELD => $value], [self::FIELD], $locale)[self::FIELD];
    }

    // What the language screen offers: what that language already says, or the typed text between brackets where it says nothing yet
    public function promptValue(Config $config, string $locale): ?string
    {
        $id = $config->getId();
        $written = null !== $id ? ($this->contentTranslator->values(self::OWNER, $id, $locale)[self::FIELD] ?? null) : null;

        return null !== $written && '' !== $written ? $written : ContentTranslator::prompt($config->getValue());
    }

    // Hands what the language screen wrote over to be stored on the flush that saves the setting, the bracketed source counting as nothing written
    public function stage(Config $config, string $locale, ?string $value): void
    {
        $id = $config->getId();
        if (null === $id) {
            return;
        }

        $this->contentTranslator->stage(self::OWNER, $id, $locale, [
            self::FIELD => ContentTranslator::untouched($value, $config->getValue()) ? null : $value,
        ]);
    }

    // The whole map is read ahead of the first slug asked for, and so are the translations behind it: a page reading ten settings would otherwise cost ten queries, translate() descending on findValues() once per id
    private function idFor(string $slug, ?string $locale = null): ?int
    {
        $this->ids ??= $this->configRepository->idsBySlug();
        $this->contentTranslator->preload(self::OWNER, array_values($this->ids), $locale);

        return $this->ids[$slug] ?? null;
    }
}
