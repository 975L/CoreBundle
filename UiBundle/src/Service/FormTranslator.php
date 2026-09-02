<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Service;

use c975L\UiBundle\Entity\Form;
use c975L\UiBundle\Entity\FormField;
use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Entity\Translation;

/**
 * What a form says in another language: the words an admin typed in the form builder.
 *
 * A form is one form in every language - one definition, one set of fields, one row apiece - and its translations
 * live beside it rather than in a second form, exactly as a page's do (see SiteBundle's PageTranslator). Form::$name
 * being unique site-wide, a second row was never an option anyway: there is one "contact" form, not one per language.
 *
 * Only what a visitor reads is covered. A field's "name" is its programmatic key (the HTML input name, the key in the
 * notification email), an expression reads outputs by that same name, and both would break the day a language changed
 * one - so neither is translated. Choice options are left out for now, being pairs inside a JSON column rather than a
 * column of their own; so are the links under the submit button, which live in Form::$actionConfig.
 *
 * A site declaring a single language never reads any of this: ContentTranslator short-circuits on isActive().
 */
class FormTranslator
{
    // The vocabulary these rows are named with, the way Block and Page name theirs
    public const string OWNER_FIELD = Translation::OWNER_FORM_FIELD;

    public const string OWNER_OUTPUT = Translation::OWNER_FORM_OUTPUT;

    // What a translation may cover of a field: what a visitor reads above the input, inside it, and what it starts on. Never "name", "type" or any bound, which are not words
    public const array FIELD_FIELDS = ['label', 'placeholder', 'defaultValue'];

    // What a translation may cover of a result: the words around the number, the number itself being formatted from the locale (see ExpressionEvaluator::format)
    public const array OUTPUT_FIELDS = ['label', 'unit'];

    // Optional, the way BlockType takes its own: without one, every text answers as it was written, which is what a site declaring a single language gets
    public function __construct(
        private readonly ?ContentTranslator $contentTranslator = null,
    ) {
    }

    public function isActive(): bool
    {
        return $this->contentTranslator?->isActive() ?? false;
    }

    /**
     * The languages a form may be translated into, besides the one it was written in.
     *
     * @return list<string>
     */
    public function getTranslatableLocales(): array
    {
        return $this->contentTranslator?->getTranslatableLocales() ?? [];
    }

    /**
     * Reads ahead a whole form's translations, so rendering it costs two queries rather than one per row.
     *
     * Called by whatever is about to build the form (see FormSubmissionType): every getLabel() below then answers
     * from the cache ContentTranslator already holds.
     */
    public function preload(Form $form, ?string $locale = null): void
    {
        $this->preloadFields($form->getFields(), $locale);
        $this->preloadOutputs($form->getOutputs(), $locale);
    }

    /**
     * @param iterable<FormField> $fields
     */
    public function preloadFields(iterable $fields, ?string $locale = null): void
    {
        if ($this->isActive()) {
            $this->contentTranslator?->preload(self::OWNER_FIELD, $this->ids($fields), $locale);
        }
    }

    /**
     * @param iterable<FormOutput> $outputs
     */
    public function preloadOutputs(iterable $outputs, ?string $locale = null): void
    {
        if ($this->isActive()) {
            $this->contentTranslator?->preload(self::OWNER_OUTPUT, $this->ids($outputs), $locale);
        }
    }

    public function getLabel(FormField | FormOutput $row, ?string $locale = null): ?string
    {
        return $this->translated($row, 'label', $locale);
    }

    public function getPlaceholder(FormField $field, ?string $locale = null): ?string
    {
        return $this->translated($field, 'placeholder', $locale);
    }

    public function getDefaultValue(FormField $field, ?string $locale = null): ?string
    {
        return $this->translated($field, 'defaultValue', $locale);
    }

    public function getUnit(FormOutput $output, ?string $locale = null): ?string
    {
        return $this->translated($output, 'unit', $locale);
    }

    /**
     * Every language this row has been given, for the screen that writes them.
     *
     * @return array<string, array<string, string|null>> locale => field => value
     */
    public function all(FormField | FormOutput $row): array
    {
        return null === $row->getId() || null === $this->contentTranslator ? [] : $this->contentTranslator->all($this->owner($row), $row->getId());
    }

    /**
     * What a language screen offers for each translatable text: what that language already says, or the source text
     * between brackets where it says nothing yet.
     *
     * @return array<string, string|null> field => value
     */
    public function promptValues(FormField | FormOutput $row, string $locale): array
    {
        $written = $this->all($row)[$locale] ?? [];
        $source = $this->source($row);

        $values = [];
        foreach ($this->fields($row) as $field) {
            $translated = $written[$field] ?? null;
            $values[$field] = null !== $translated && '' !== $translated
                ? $translated
                : ContentTranslator::prompt($source[$field] ?? null);
        }

        return $values;
    }

    /**
     * Hands what a language screen wrote over to be stored on the flush that saves the form, a field left holding the
     * bracketed source counting as nothing written (see ContentTranslator::stage).
     *
     * @param array<string, string|null> $values field => value
     */
    public function stage(FormField | FormOutput $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null === $id) {
            return;
        }

        $source = $this->source($row);

        $staged = [];
        foreach ($this->fields($row) as $field) {
            if (!array_key_exists($field, $values)) {
                continue;
            }

            $staged[$field] = ContentTranslator::untouched($values[$field], $source[$field] ?? null) ? null : $values[$field];
        }

        if ([] !== $staged) {
            $this->contentTranslator?->stage($this->owner($row), $id, $locale, $staged);
        }
    }

    /**
     * Writes a translation straight away rather than staging it - what a seeder does, having no form to wait for.
     *
     * @param array<string, string|null> $values field => value
     */
    public function store(FormField | FormOutput $row, string $locale, array $values): void
    {
        $id = $row->getId();
        if (null !== $id) {
            $this->contentTranslator?->store($this->owner($row), $id, $locale, $values);
        }
    }

    // One text in the language being read, or null when this language says nothing and the source is to be used as is
    private function translated(FormField | FormOutput $row, string $field, ?string $locale): ?string
    {
        $source = $this->source($row);

        if (null === $this->contentTranslator) {
            return $source[$field] ?? null;
        }

        return $this->contentTranslator->translate($this->owner($row), $row->getId(), $source, [$field], $locale)[$field] ?? null;
    }

    /**
     * @param iterable<FormField|FormOutput> $rows
     *
     * @return list<int>
     */
    private function ids(iterable $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (null !== $row->getId()) {
                $ids[] = $row->getId();
            }
        }

        return $ids;
    }

    private function owner(FormField | FormOutput $row): string
    {
        return $row instanceof FormField ? self::OWNER_FIELD : self::OWNER_OUTPUT;
    }

    /**
     * @return list<string>
     */
    private function fields(FormField | FormOutput $row): array
    {
        return $row instanceof FormField ? self::FIELD_FIELDS : self::OUTPUT_FIELDS;
    }

    /**
     * The text this row was written in, which a translation lays over.
     *
     * @return array<string, string|null>
     */
    private function source(FormField | FormOutput $row): array
    {
        return $row instanceof FormField
            ? ['label' => $row->getLabel(), 'placeholder' => $row->getPlaceholder(), 'defaultValue' => $row->getDefaultValue()]
            : ['label' => $row->getLabel(), 'unit' => $row->getUnit()];
    }
}
