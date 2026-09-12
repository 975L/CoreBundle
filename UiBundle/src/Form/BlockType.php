<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use c975L\UiBundle\Form\Util\MultiUploadMerger;
use c975L\UiBundle\Form\Util\SubmissionIntegrity;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\MediaTranslator;
use c975L\UiBundle\Service\TranslationFormContext;
use c975L\UiBundle\Service\VideoPosterImporter;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PostSetDataEvent;
use Symfony\Component\Form\Event\PostSubmitEvent;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Count;

// Check Readme for usage instructions
class BlockType extends AbstractType
{
    // "hero"'s pure-CSS crossfade slideshow only has :nth-child/[data-count] rules for up to this many images (see .hero__media--slideshow in sass/_page-sections.scss) - beyond it, extra images would silently collide with an earlier slide's animation timing instead of taking their own turn. The cap is shared with the "grid" mediaLayout, which has no timing to collide with: one number covering both is worth more than a validation branch reading a sibling field from inside this form's PRE_SUBMIT dance
    private const int HERO_MEDIA_MAX = 9;

    // Unmapped marker rendered beside a container's "slots" collection, and read back before anything is pruned: it is what tells "the editor removed the last slot" apart from "this form never carried the collection".
    // Outside the collection's own rows on purpose, so deleting every one of them leaves it standing
    public const SLOTS_RENDERED = 'slotsRendered';

    // How a language screen names the sub-form carrying one media's texts. A name of its own, never "medias": that collection has a choreography of its own around add/remove and file uploads (see onPreSubmit), which a translation has no business crossing
    private const string MEDIA_TRANSLATION_PREFIX = 'mediaTranslation_';

    // The repeated texts each block held before this submission, by block id: a translation names the place it sits at ("cards.0.title"), so a card that moved would leave its own on whatever card took its index. Read in PRE_SUBMIT, where the form still holds the row as it was
    /** @var array<int, array<string, string|null>> */
    private array $sourceBeforeSubmit = [];

    // The proof that the screen really rendered those sub-forms, on the pattern SLOTS_RENDERED already sets. A child a theme never rendered is submitted as null all the same, which reads exactly like a field an editor emptied on purpose - and stored, it would take away on every save what the language screen or the translation pass had written. Nothing is staged unless this comes back
    private const string MEDIA_TRANSLATIONS_RENDERED = 'mediaTranslationsRendered';

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly UrlGeneratorInterface $router,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?VideoPosterImporter $videoPosterImporter = null,
        private readonly ?ContentTranslator $contentTranslator = null,
        private readonly ?TranslationFormContext $translationFormContext = null,
        private readonly ?MediaTranslator $mediaTranslator = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Said once for the whole screen: the form theme that draws Donovan's toolbar sits several levels below the sub-form this language applies to, too far to be handed the option (see TranslationFormContext)
        if (null !== $options['translation_locale']) {
            $this->translationFormContext?->set($options['translation_locale']);
        }

        $this->addKindField($builder, $options['context']);

        $builder->add('position', HiddenType::class, [
            'attr' => ['class' => 'ui-sort-position'],
        ]);

        // A checkbox rather than a HiddenType, for the mapping alone: unchecked, a checkbox is simply not submitted and maps to false, where a hidden input would post the string "1"/"" onto a bool property. Its row is never shown - the state is toggled by the eye button of the row's own toolbar (see block-hide.js), not by a checkbox sitting among the kind's fields
        $builder->add('hidden', CheckboxType::class, [
            'required' => false,
            'label' => false,
            'attr' => ['class' => 'ui-block-hidden'],
            'row_attr' => ['class' => 'd-none'],
        ]);

        // Load the sub-form `data` dynamically according to the block kind
        $builder->addEventListener(FormEvents::PRE_SET_DATA, fn (PreSetDataEvent $event) => $this->onPreSetData($event, $options['context'], $options['translation_locale']));

        // Filter the collections of a language screen again, once Symfony has given "data" its own data: that is when a collection builds its entries (ResizeFormListener, on POST_SET_DATA), and an unmapped sub-form is given its data by the mapper this event follows - so whatever was taken off an entry beforehand is put back moments later
        if (null !== $options['translation_locale']) {
            $builder->addEventListener(FormEvents::POST_SET_DATA, fn (PostSetDataEvent $event) => $this->keepTranslatableCollections($event));
        }

        // Re-add the sub-form `data` BEFORE Symfony maps submitted values (PRE_SUBMIT), so the correct FormType is in place when the mapping happens.
        $builder->addEventListener(FormEvents::PRE_SUBMIT, fn (FormEvent $event) => $this->onPreSubmit($event, $options['translation_locale']));

        // Here rather than in a Doctrine listener (unlike the nocookie rewrite, see BlockVideoNoCookieListener): the import sets a Vich file field, which has to be in place before Vich's own prePersist/preUpdate listener runs, not alongside it
        // Runs whatever the submission turns out to be worth - this form is always a CollectionType entry, so its POST_SUBMIT precedes the root form's validation. Nothing is flushed here, and a rejected submission's downloaded still is swept on kernel.terminate (see VideoPosterImporter::removeTemporaryFiles)
        $builder->addEventListener(FormEvents::POST_SUBMIT, fn (PostSubmitEvent $event) => $this->onPostSubmit($event, $options['translation_locale']));
    }

    // Load the sub-form `data` dynamically according to the block kind
    private function onPreSetData(PreSetDataEvent $event, mixed $context, ?string $translationLocale = null): void
    {
        $block = $event->getData();

        CollectionReconciler::addIdField($event->getForm(), $block instanceof Block ? $block->getId() : null);

        // Read ahead for the whole tree, so the fields below - this block and, for a container, every one of its slots - are filled from one query rather than one apiece
        if (null !== $translationLocale && $block instanceof Block) {
            $this->contentTranslator?->preloadBlocks([$block], $translationLocale);
        }

        $kind = null === $block ? null : (is_object($block) ? $block->getKind() : ($block['kind'] ?? null));
        if ($kind && $this->registry->has($kind)) {
            $this->addKindSubForms($event->getForm(), $kind, $block instanceof Block ? $block : null, $context, $translationLocale);
        }

        // Added last (after "data", not statically at the top of buildForm) so it always renders below the kind-specific fields (e.g. MenuLinkType's "target") instead of between "kind" and "data"
        // An entrance effect plays the same in every language: left off a language screen, where it would be offered per language and read from one
        if (null === $translationLocale) {
            $this->addAnimationField($event->getForm());
        }
    }

    // Everything a kind brings with it: its picker, its own fields, its medias and, for a container, its slots
    private function addKindSubForms(FormInterface $form, string $kind, ?Block $block, mixed $context, ?string $translationLocale): void
    {
        $this->addKindField($form, $context, $kind, null !== $translationLocale);
        $this->addDataSubForm($form, $kind, $block, $translationLocale);

        // An image is the same image in every language, so a language screen never renders the rows themselves - what it does offer is the texts hanging from them, which are read by a visitor like any other prose (see addMediaTranslationSubForms)
        if (null === $translationLocale && $this->registry->hasMediaTypes($kind)) {
            $this->addMediaSubForm($form, $kind);
        } elseif (null !== $translationLocale && null !== $block) {
            $this->addMediaTranslationSubForms($form, $block, $translationLocale);
        }

        if ($this->registry->isContainer($kind)) {
            $this->addSlotsSubForm($form, $kind, $block, $translationLocale);
        }
    }

    // Re-adds the sub-form `data` before Symfony maps submitted values, so the correct FormType is in place when the mapping happens
    private function onPreSubmit(FormEvent $event, ?string $translationLocale = null): void
    {
        $submitted = $event->getData();
        $kind = $submitted['kind'] ?? null;
        if (!$kind || !$this->registry->has($kind)) {
            return;
        }

        // Narrowed once: a brand new collection entry has no block behind it yet, and every step below reads it the same way
        $data = $event->getForm()->getData();
        $block = $data instanceof Block ? $data : null;

        // Already cached by the render that put this form on screen, save for a block the submission itself brings in
        if (null !== $translationLocale && null !== $block) {
            $this->contentTranslator?->preloadBlocks([$block], $translationLocale);
        }

        $this->addDataSubForm($event->getForm(), $kind, $block, $translationLocale);

        // Picking another kind swaps the "data" sub-form client-side (see block.js) and nothing else: whatever the previous kind rendered around it - the per-image metadata of its medias, its slots - is still in the DOM and still posted. A key no field claims fails the whole form with "This form should not contain extra fields", which is why switching a kind used to take two saves, the browser having re-rendered the form for the new kind on the failed one, losing whatever was typed in between. What the new kind cannot receive is dropped from the submission below, here rather than client-side so a submission that never went through the picker is covered too
        $previousKind = $block?->getKind();
        $kindChanged = null !== $previousKind && $previousKind !== $kind;

        // Everything a language screen has no part in: its medias were left out of the render, and the snapshot below belongs to the screen the block is written on
        if (null === $translationLocale) {
            $this->applySubmittedWritingScreen($event, $kind, $block, $kindChanged);
        }

        $this->applySubmittedContainer($event, $kind, $kindChanged, $translationLocale);

        // "data" (and "medias"/"slots") were just (re)added above - move "animation" back below them, in case this is a brand new collection entry whose PRE_SET_DATA fired with no kind yet (so "animation" was added there before "data" ever existed)
        // Left out of a language screen exactly as it was left out of the render, or the field would be declared with nothing submitted for it
        if (null === $translationLocale) {
            $event->getForm()->remove('animation');
            $this->addAnimationField($event->getForm());
        }
    }

    // What only the screen a block is written on does with a submission: re-adding the media sub-form against a submission that never carried one has CollectionType's resize listener read it as "every row removed", and the block's files go with them at flush
    private function applySubmittedWritingScreen(FormEvent $event, string $kind, ?Block $block, bool $kindChanged): void
    {
        $this->applySubmittedMedias($event, $kind, $kindChanged);

        // Snapshot before anything is mapped, for the purge that runs once the new data is in (see purgeMovedCollectionTranslations)
        if (null !== $block && null !== $block->getId()) {
            $this->sourceBeforeSubmit[$block->getId()] = $this->collectionTexts($kind, $block->getData());
        }
    }

    // The medias the new kind can receive, the ones it cannot being taken off the submission and off the form
    private function applySubmittedMedias(FormEvent $event, string $kind, bool $kindChanged): void
    {
        $submitted = $event->getData();

        if (!$this->registry->hasMediaTypes($kind)) {
            if ($kindChanged) {
                // The medias themselves are deliberately left on the block rather than removed: the new kind simply doesn't render them, and switching back brings them - their files included - straight back. Which takes both the submitted keys AND the fields PRE_SET_DATA declared for the previous kind: an absent key does not make Symfony skip a declared child, it submits it with null, and CollectionType's resize listener reads that as "every row removed" - cascade-removed and orphan-removed at flush
                unset($submitted['medias'], $submitted['mediaUpload']);
                $event->setData($submitted);
                $event->getForm()->remove('medias');
                $event->getForm()->remove('mediaUpload');
            }

            return;
        }

        $block = $event->getForm()->getData();
        if ($block instanceof Block) {
            $submitted = $this->reconcileSubmittedMedias($submitted, $block);
        }

        // Removing the very last media also leaves nothing submitted at all under "medias" (an HTML form can't represent an empty array, only an absent key), which has to be normalized to [] below or Symfony skips add/remove handling for the field.
        $submitted['medias'] ??= [];
        $submitted = $this->mergeMultiUpload($submitted, $kind);
        $this->addMediaSubForm($event->getForm(), $kind);

        // Each kind builds its media rows with its own set of fields (a card's teaser image has no caption, no dimensions, no credits - see MediaUploadType), so the rows left behind by the previous one carry keys the new one never declares
        if ($kindChanged) {
            $submitted['medias'] = $this->dropForeignEntryKeys($submitted['medias'], $event->getForm()->get('medias'));
        }

        $event->setData($submitted);
    }

    // What the block still holds against what came back, the rows the editor deleted taken off both sides
    private function reconcileSubmittedMedias(array $submitted, Block $block): array
    {
        CollectionReconciler::pruneRemoved(
            $block->getMedias(),
            $submitted['medias'] ?? [],
            static fn (Media $media) => $block->removeMedia($media)
        );

        // A deleted media can leave a malformed remnant behind in the submission (its "delete"/css-class checkboxes resubmitted under the old array key, with no id and no actual file) - left as-is, CollectionType treats it as a genuine new entry and binds it to null data, which breaks Vich's own conditional "delete" checkbox (VichFileType only adds it when the bound object is non-null) and fails validation with "This form should not contain extra fields".
        $submitted['medias'] = CollectionReconciler::dropOrphaned(
            $submitted['medias'] ?? [],
            $block->getMedias(),
            static fn (array $entry): bool => !empty($entry['file']['file'] ?? null)
        );

        return $submitted;
    }

    // The slots of a container, or what a kind switched away from one leaves behind
    private function applySubmittedContainer(FormEvent $event, string $kind, bool $kindChanged, ?string $translationLocale = null): void
    {
        if ($this->registry->isContainer($kind)) {
            $this->applySubmittedSlots($event, $kind, $translationLocale);

            return;
        }

        $submitted = $event->getData();
        if (!$kindChanged && !isset($submitted[self::SLOTS_RENDERED]) && !isset($submitted['slots'])) {
            return;
        }

        // Switched away from a container: its slots collection and the marker beside it are still posted, with no field left to claim either. Taken off the form as well as out of the submission, and the slots kept on the block, for the very same reasons as its medias
        unset($submitted['slots']);
        $event->setData($submitted);
        $event->getForm()->remove('slots');

        // Added back for a kind switched away from a container, which still carries the marker: a key no field claims fails the whole form as an extra one
        if (isset($submitted[self::SLOTS_RENDERED])) {
            $this->addSlotsMarker($event->getForm());
        }
    }

    // Here rather than in a Doctrine listener (unlike the nocookie rewrite, see BlockVideoNoCookieListener): the import sets a Vich file field, which has to be in place before Vich's own prePersist/preUpdate listener runs, not alongside it
    private function onPostSubmit(PostSubmitEvent $event, ?string $translationLocale = null): void
    {
        $block = $event->getData();
        if (!$block instanceof Block) {
            return;
        }

        if (null !== $translationLocale) {
            $this->stageTranslations($event->getForm(), $block, $translationLocale);
            $this->stageMediaTranslations($event->getForm(), $block, $translationLocale);

            return;
        }

        $this->purgeMovedCollectionTranslations($block);

        $this->videoPosterImporter?->importIfRequested($block);
    }

    // Drops the translations of a repeated text the source no longer says at that place: the text the site is written in is the msgid, and when it moves its translation goes with it - the rule untouched() applies to a single field
    private function purgeMovedCollectionTranslations(Block $block): void
    {
        $id = $block->getId();
        $before = null === $id ? null : ($this->sourceBeforeSubmit[$id] ?? null);

        if (null === $id || null === $before || null === $this->contentTranslator) {
            return;
        }

        unset($this->sourceBeforeSubmit[$id]);

        $gone = $this->movedFields($before, $this->collectionTexts((string) $block->getKind(), $block->getData()));

        foreach ([] === $gone ? [] : $this->contentTranslator->getTranslatableLocales() as $locale) {
            $this->contentTranslator->stage(Translation::OWNER_BLOCK, $id, $locale, $gone);
        }
    }

    // The places the source no longer says what it said, staged as nothing: compared on the words alone, as every other source/translation comparison here is, a raw !== reading a card nobody touched as a rewritten one
    /**
     * @param array<string, string|null> $before
     * @param array<string, string|null> $after
     *
     * @return array<string, null>
     */
    private function movedFields(array $before, array $after): array
    {
        $plain = static fn (?string $text): ?string => null === $text ? null : ContentTranslator::plain($text);

        $gone = [];
        foreach ($before as $field => $text) {
            if ($plain($after[$field] ?? null) !== $plain($text)) {
                $gone[$field] = null;
            }
        }

        return $gone;
    }

    // The repeated texts of a block's data, flat: "cards.0.title" => the words it holds
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, string|null>
     */
    private function collectionTexts(string $kind, array $data): array
    {
        $texts = [];

        foreach ($this->registry->getTranslatableCollections($kind) as $collection => $keys) {
            foreach (is_array($data[$collection] ?? null) ? $data[$collection] : [] as $index => $entry) {
                foreach (is_array($entry) ? $keys : [] as $key) {
                    $texts[$collection . '.' . $index . '.' . $key] = is_string($entry[$key] ?? null) ? $entry[$key] : null;
                }
            }
        }

        return $texts;
    }

    // Hands what was just written over to ContentTranslator, which keeps it until the flush that saves the block
    // A field handed back still holding the bracketed source is taken back to null, or it would come out on the front as a translation reading "[Bonjour]"
    private function stageTranslations(FormInterface $form, Block $block, string $locale): void
    {
        $id = $block->getId();
        if (null === $id || null === $this->contentTranslator || !$form->has('data')) {
            return;
        }

        $submitted = $form->get('data')->getData();
        if (!is_array($submitted)) {
            return;
        }

        $kind = (string) $block->getKind();
        $original = $block->getData();
        $values = [];
        foreach ($this->registry->getTranslatable($kind) as $field) {
            if (!array_key_exists($field, $submitted)) {
                continue;
            }

            $value = $submitted[$field];
            $values[$field] = ContentTranslator::untouched($value, $original[$field] ?? null) ? null : $value;
        }

        $values += $this->stagedCollectionValues($kind, $original, $submitted);

        if ([] !== $values) {
            $this->contentTranslator->stage(Translation::OWNER_BLOCK, $id, $locale, $values);
        }
    }

    // The repeated texts a language screen just wrote, flattened one entry at a time and only for the entries the source still holds: the screen is unmapped, so a card added there writes nothing anyway, and a row named after it would sit in the table for good
    /**
     * @param array<string, mixed> $original
     * @param array<string, mixed> $submitted
     *
     * @return array<string, string|null>
     */
    private function stagedCollectionValues(string $kind, array $original, array $submitted): array
    {
        $values = [];

        foreach ($this->registry->getTranslatableCollections($kind) as $collection => $keys) {
            foreach (is_array($original[$collection] ?? null) ? $original[$collection] : [] as $index => $entry) {
                foreach (is_array($entry) ? $keys : [] as $key) {
                    if (!array_key_exists($key, $submitted[$collection][$index] ?? [])) {
                        continue;
                    }

                    $value = $submitted[$collection][$index][$key];
                    $values[$collection . '.' . $index . '.' . $key] = ContentTranslator::untouched($value, $entry[$key] ?? null) ? null : $value;
                }
            }
        }

        return $values;
    }

    // One sub-form per media the block hangs, each carrying that media's own texts in the language being written, named by the media's id rather than gathered in a collection: nothing is added or removed here - a media is put on a block once, in the language it was composed in - so there is no resize listener to satisfy and reading the submission back is a lookup rather than a walk
    private function addMediaTranslationSubForms(FormInterface $form, Block $block, string $locale): void
    {
        if (null === $this->mediaTranslator) {
            return;
        }

        // Read ahead, so a grid of a dozen cards costs the one query its block already ran rather than one per card (see ContentTranslator::preload, which dedupes against what is already loaded)
        $this->mediaTranslator->preload($block->getMedias(), $locale);

        $rendered = false;

        foreach ($block->getMedias() as $media) {
            $id = $media->getId();
            if (null === $id) {
                continue;
            }

            $values = $this->mediaTranslator->promptValues($media, $locale);

            // Nothing written in any of the three: a decorative image, a slider's slide, a video poster - there is no msgid to offer a language
            if ([] === array_filter($values, static fn (?string $value): bool => null !== $value)) {
                continue;
            }

            $form->add(self::MEDIA_TRANSLATION_PREFIX . $id, MediaTranslationType::class, [
                // Named by what it says rather than by its id: an editor reads the cards of a grid, not their primary keys
                'label' => $media->getUntranslated('label') ?: $media->getFilename(),
                // A caption is an editor's own words, not a key: a "%" in one would otherwise pass for a parameter
                'translation_domain' => false,
                'mapped' => false,
                'required' => false,
                'data' => $values,
                'media' => $media,
                // Named so a guided step has something to point at: the screen where a card's own texts are written (see the page translation project)
                'attr' => ['data-media-translation' => true],
            ]);

            $rendered = true;
        }

        if ($rendered) {
            $form->add(self::MEDIA_TRANSLATIONS_RENDERED, HiddenType::class, ['mapped' => false, 'data' => '1']);
        }
    }

    // Hands each media's submitted texts over to be stored on the flush that saves the block, the same way its own data is (see stageTranslations)
    private function stageMediaTranslations(FormInterface $form, Block $block, string $locale): void
    {
        // Nothing rendered, nothing written: a submission that never carried the marker never carried the texts either, and staging what it did not say would take them away (see MEDIA_TRANSLATIONS_RENDERED)
        if (null === $this->mediaTranslator || !$form->has(self::MEDIA_TRANSLATIONS_RENDERED) || null === $form->get(self::MEDIA_TRANSLATIONS_RENDERED)->getData()) {
            return;
        }

        foreach ($block->getMedias() as $media) {
            $name = self::MEDIA_TRANSLATION_PREFIX . $media->getId();
            if (!$form->has($name)) {
                continue;
            }

            $submitted = $form->get($name)->getData();
            if (is_array($submitted)) {
                $this->mediaTranslator->stage($media, $locale, $submitted);
            }
        }
    }

    // What a language has to say, or the source text between brackets when it has said nothing yet - which is both the thing to translate and the mark of a field nobody has
    private function translationValues(Block $block, string $kind, string $locale): array
    {
        $original = $block->getData();
        $stored = $this->contentTranslator?->values(Translation::OWNER_BLOCK, (int) $block->getId(), $locale) ?? [];

        foreach ($this->registry->getTranslatable($kind) as $field) {
            $original[$field] = $this->promptValue($stored[$field] ?? null, $original[$field] ?? null);
        }

        return $this->promptCollections($kind, $original, $stored);
    }

    // The same, entry by entry: a card's title opens on what that language says of that card, or on its own words between brackets
    /**
     * @param array<string, mixed>       $original
     * @param array<string, string|null> $stored
     *
     * @return array<string, mixed>
     */
    private function promptCollections(string $kind, array $original, array $stored): array
    {
        foreach ($this->registry->getTranslatableCollections($kind) as $collection => $keys) {
            if (!is_array($original[$collection] ?? null)) {
                continue;
            }

            foreach ($original[$collection] as $index => $entry) {
                foreach (is_array($entry) ? $keys : [] as $key) {
                    $original[$collection][$index][$key] = $this->promptValue($stored[$collection . '.' . $index . '.' . $key] ?? null, $entry[$key] ?? null);
                }
            }
        }

        return $original;
    }

    // What that language already says, or the source text between brackets where it says nothing yet
    private function promptValue(mixed $translated, mixed $source): ?string
    {
        return is_string($translated) && '' !== $translated ? $translated : ContentTranslator::prompt($source);
    }

    // "slots" absent from the submission means one of two very different things: the editor removed the last slot - an HTML form cannot represent an empty array, only an absent key, same as "medias" - or this form never carried the collection at all. A kind just switched to a container client-side, a body PHP truncated at max_input_vars, a DOM something rewrote: all three arrive as that same absent key. Read as the first, the second deletes every slot, cascaded and orphan-removed, with nothing left to say it ever happened - which is how a live page lost the cards of a section_cards block. The marker is what tells the two apart. The submitted collection itself counts as its own proof: an edit page opened before the marker existed still carries its slots, and must go on saving rather than fail as an extra field. "Neither", and a body PHP cut short, both mean nothing submitted says what the editor removed.
    private function applySubmittedSlots(FormEvent $event, string $kind, ?string $translationLocale = null): void
    {
        $submitted = $event->getData();
        $form = $event->getForm();
        $block = $form->getData();

        $rendered = isset($submitted[self::SLOTS_RENDERED]) || isset($submitted['slots']);
        $complete = $rendered && !$this->isTruncatedSubmission();

        // Leaving the collection untouched means taking it off the form: PRE_SET_DATA already added it, and an absent key does not make Symfony skip a declared child - it submits it with null, which CollectionType's ResizeFormListener reads as "every row removed", the mapper then empties the collection and orphanRemoval deletes the rows at flush. Whatever partial rows a truncated body carried are dropped with it, so the submission fails on its own errors rather than on an extra field, and PageCrudController is what refuses it outright. The marker is put back on its own, being no proof of anything by itself
        if (!$complete) {
            $form->remove('slots');
            unset($submitted['slots']);
            $event->setData($submitted);
            $this->addSlotsMarker($form);

            return;
        }

        // Nothing is added or removed from a language screen, which offers neither (see addSlotsSubForm): the page is built once and translated afterwards, and a slot taken away there would be taken away from every language at once
        if (null === $translationLocale && $block instanceof Block) {
            CollectionReconciler::pruneRemoved(
                $block->getSlots(),
                $submitted['slots'] ?? [],
                static fn (Block $slot) => $block->removeSlot($slot)
            );
        }

        // Removing the very last slot leaves nothing submitted at all under "slots", which has to be normalized to [] here or Symfony skips add/remove handling for the field
        if (!isset($submitted['slots'])) {
            $submitted['slots'] = [];
            $event->setData($submitted);
        }

        $this->addSlotsSubForm($form, $kind, $block instanceof Block ? $block : null, $translationLocale);
    }

    // Drops from each submitted row whatever key the collection's entry form does not declare - see the kind-change note in PRE_SUBMIT. Read off the collection's own prototype, built from the very entry type (and options) every row is built from, so no list of field names has to be kept in sync here
    private function dropForeignEntryKeys(array $entries, FormInterface $collection): array
    {
        $prototype = $collection->getConfig()->getAttribute('prototype', null);
        if (!$prototype instanceof FormInterface) {
            return $entries;
        }

        $allowed = [];
        foreach ($prototype as $name => $child) {
            $allowed[$name] = true;
        }

        foreach ($entries as $index => $entry) {
            if (is_array($entry)) {
                $entries[$index] = array_intersect_key($entry, $allowed);
            }
        }

        return $entries;
    }

    // The kind picker, added from buildForm() and rebuilt from PRE_SET_DATA when the block already holds a kind
    private function addKindField(FormBuilderInterface | FormInterface $form, ?string $context, ?string $legacyKind = null, bool $locked = false): void
    {
        // Locked on a language screen: the kind is what the block is, not what it says, and switching it there would swap the fields of every language at once. Offered as the one choice it already holds rather than disabled, a disabled field being left out of the submission - which is what PRE_SUBMIT reads to know which sub-form to put back
        if ($locked && null !== $legacyKind) {
            $form->add('kind', ChoiceType::class, [
                'label' => 'label.block_kind',
                'choices' => [$this->registry->getLabel($legacyKind) => $legacyKind],
                'choice_translation_domain' => false,
                'attr' => ['readonly' => true],
                'row_attr' => ['data-kind-row' => ''],
            ]);

            return;
        }

        $choices = $this->registry->groupedByCategory($context);

        // A kind its context no longer lists is put back for this one form, else ChoiceType would reject it on submit and lock the editor out
        $isLegacy = null !== $legacyKind && !$this->registry->isAllowedInContext($legacyKind, $context);
        if ($isLegacy) {
            $choices[$this->registry->getCategory($legacyKind)][$this->registry->getLabel($legacyKind)] = $legacyKind;
        }

        $form->add('kind', ChoiceType::class, [
            'label' => 'label.block_kind',
            'help' => $isLegacy ? 'label.block_kind_legacy_slot_help' : null,
            // Both legacy-kind messages are warnings, not neutral field hints - the markup carrying that is in the translation, so each locale keeps a single string to review
            'help_html' => true,
            'choices' => $choices,
            'choice_translation_domain' => false,
            // Carried onto each <option> for the visual picker built over this select (see block-picker.js), which gives the label and the description a line each - the choice label itself is the two run together ("Label (description)"), the only way a bare <select> can say what a kind does, and printing that in the palette would repeat the description twice over. Nothing but the picker reads these.
            'choice_attr' => fn (string $kind): array => [
                'data-label' => $this->registry->getLabel($kind),
                'data-description' => $this->registry->getDescription($kind),
            ],
            'placeholder' => 'label.choose_block_kind',
            'attr' => [
                'data-controller' => 'block',
                'data-block-kind-url-value' => $this->router->generate('ui_block_data_form'),
                'data-action' => 'change->block#loadData',
            ],
            'row_attr' => ['data-kind-row' => ''],
        ]);
    }

    private function addAnimationField(FormInterface $form): void
    {
        $form->add('animation', AnimationChoiceType::class, [
            'row_attr' => ['data-animation-row' => ''],
        ]);
    }

    private function addDataSubForm(FormInterface $form, string $kind, ?Block $block = null, ?string $translationLocale = null): void
    {
        $options = [
            'label' => false,
            'row_attr' => ['class' => 'block-data-form'],
            // One prefix shared by every kind's own FormType, so the layout below can be themed once for all of them rather than per kind (see form/block_theme.html.twig, "ui_block_data_widget"). Themed rather than written in form/block.html.twig, which only ever renders the fragment the kind picker loads over AJAX - the edit screen itself is rendered by EasyAdmin, which knows nothing of that template
            'block_prefix' => 'ui_block_data',
        ];

        $translating = null !== $translationLocale && $block instanceof Block && null !== $block->getId();

        // Unmapped on purpose: what an editor writes in another language belongs to the translation table, and mapped back it would overwrite the text the site itself was written in
        if ($translating) {
            $options['mapped'] = false;
            $options['data'] = $this->translationValues($block, $kind, (string) $translationLocale);
        }

        $form->add('data', $this->registry->getFormClass($kind), $options);

        if ($translating) {
            $this->keepTranslatableFields($form->get('data'), $kind);
        }
    }

    // The other half of keepTranslatableFields, run late: a collection's entries do not exist when "data" is added, and are rebuilt from scratch every time it is given its data, so the entries are pruned here rather than there
    private function keepTranslatableCollections(PostSetDataEvent $event): void
    {
        $block = $event->getData();
        $form = $event->getForm();

        if (!$block instanceof Block || !$form->has('data')) {
            return;
        }

        $kind = (string) $block->getKind();
        $data = $form->get('data');

        foreach ($this->registry->getTranslatableCollections($kind) as $collection => $keys) {
            if ($data->has($collection)) {
                $this->keepTranslatableEntries($data->get($collection), $keys);
            }
        }
    }

    // A language screen offers what a language can change and nothing else: a colour, a link target or an image has one value for the whole site, and rendering it here would invite an editor to set it per language, which nothing would then read
    private function keepTranslatableFields(FormInterface $data, string $kind): void
    {
        $translatable = $this->registry->getTranslatable($kind);
        $collections = $this->registry->getTranslatableCollections($kind);

        // Locked before that read, while the collections still hold no entry: their size is what a language screen must not change, and rebuilding one afterwards would throw away the entries just built
        foreach (array_keys($collections) as $collection) {
            if ($data->has($collection)) {
                $this->lockCollectionSize($data, $collection);
            }
        }

        // Read once, for the side effect: a collection's entries are built by its resize listener when the form holding it is given its data, and a sub-form added with a "data" option is only given it the first time it is read - lazily, at render. Without this the entries below do not exist yet, nothing is taken off them, and every field of every card comes back on submission as one the form never declared, which invalidates the whole page and writes nothing
        if ([] !== $collections) {
            $data->getData();
        }

        // Named first, then removed: taking children off the form being walked skips every other one
        foreach (array_keys($data->all()) as $name) {
            if (isset($collections[$name])) {
                $this->keepTranslatableEntries($data->get($name), $collections[$name]);

                continue;
            }

            if (!in_array($name, $translatable, true)) {
                $data->remove($name);
            }
        }
    }

    // A language screen offers neither Add nor Delete: a card taken away there would be taken away from every language, and one added is dropped without a word, the sub-form being unmapped. Rebuilt rather than reconfigured, a form's options being read-only once it is built
    private function lockCollectionSize(FormInterface $data, string $name): void
    {
        $collection = $data->get($name);
        $config = $collection->getConfig();

        $data->add($name, $config->getType()->getInnerType()::class, [
            ...$config->getOptions(),
            'allow_add' => false,
            'allow_delete' => false,
            'prototype' => false,
        ]);
    }

    // The same rule one level down, for a collection a kind declares repeated texts in: each entry keeps its prose and loses the rest, an icon or a pair of coordinates saying the same thing in every language. The entries themselves are left alone here, their number being locked above
    /** @param list<string> $keys */
    private function keepTranslatableEntries(FormInterface $collection, array $keys): void
    {
        foreach ($collection as $entry) {
            foreach (array_keys($entry->all()) as $name) {
                if (!in_array($name, $keys, true)) {
                    $entry->remove($name);
                }
            }
        }
    }

    private function addMediaSubForm(FormInterface $form, string $kind): void
    {
        $accept = implode(',', $this->registry->getMediaTypes($kind));

        $constraints = 'hero' === $kind
            ? [new Count(max: self::HERO_MEDIA_MAX, maxMessage: 'text.hero_media_max')]
            : [];

        $form->add('medias', CollectionType::class, [
            'label' => 'label.media',
            'help' => $this->registry->getMediaHelp($kind),
            'entry_type' => MediaUploadType::class,
            'entry_options' => ['accept' => $accept, 'context' => $kind],
            'allow_add' => true,
            'allow_delete' => true,
            'by_reference' => false,
            'prototype' => true,
            'constraints' => $constraints,
        ]);

        // Unmapped: consumed directly from the submitted data by mergeMultiUpload() below (spliced into "medias" as brand new entries), never bound onto the entity itself
        if ($this->registry->allowsMultiUpload($kind)) {
            $form->add('mediaUpload', FileType::class, [
                'label' => 'label.media_multi_upload',
                'help' => 'label.media_multi_upload_help',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'attr' => array_filter(['accept' => $accept]),
            ]);
        }
    }

    // Nested Block rows, recursively through this same type; $container is passed in rather than read via getData(), which throws "A cycle was detected" inside PRE_SET_DATA
    private function addSlotsSubForm(FormInterface $form, string $kind, ?Block $container, ?string $translationLocale = null): void
    {
        // Only set once persisted: a slot can't be dragged into a container with no id to relocate against
        $containerId = $container?->getId();
        $slotContext = $this->registry->getSlotContext($kind);

        // Surfaced on the container's own row too, so the editor needn't expand every slot to find it
        $legacySlots = $this->legacySlotLabels($container, $slotContext);

        $form->add('slots', CollectionType::class, [
            'label' => 'section_cards' === $kind ? 'label.slots_cards' : 'label.slots',
            'help' => [] === $legacySlots ? null : 'label.slots_legacy_kinds_help',
            'help_html' => true,
            'help_translation_parameters' => ['%blocks%' => implode(', ', $legacySlots)],
            'entry_type' => self::class,
            // The language travels down with them: a slot rendered without it would edit itself mapped, and what an editor writes in another language would land on the text the site was written in
            'entry_options' => ['context' => $slotContext, 'translation_locale' => $translationLocale],
            // A language screen translates what is there; the page is composed once, in the language it was written in
            'allow_add' => null === $translationLocale,
            'allow_delete' => null === $translationLocale,
            'by_reference' => false,
            'prototype' => true,
            'row_attr' => null !== $containerId ? [
                'data-ui-sort-group' => BlockMoveRowAttrBuilder::GROUP,
                'data-ui-move-target' => $containerId,
            ] : [],
        ]);

        $this->addSlotsMarker($form);
    }

    // Rendered with the collection and read back on submit - see SLOTS_RENDERED. Unmapped, "data" set on the add() itself the way CollectionReconciler::addIdField() has to, the default mapper otherwise writing the field's empty "data" option back over anything set afterwards
    private function addSlotsMarker(FormInterface $form): void
    {
        $form->add(self::SLOTS_RENDERED, HiddenType::class, [
            'mapped' => false,
            'required' => false,
            'data' => '1',
        ]);
    }

    // Whether PHP cut this request body short - see SubmissionIntegrity. No request (a form built outside a request cycle, as the tests do) reads as complete: there is no truncated body to guard against
    private function isTruncatedSubmission(): bool
    {
        $request = $this->requestStack?->getCurrentRequest();

        return null !== $request && SubmissionIntegrity::isTruncated($request->request->all());
    }

    // Worded exactly as the accordion headers, so the warning names blocks the editor can actually find
    private function legacySlotLabels(?Block $container, ?string $context): array
    {
        $labels = [];
        foreach ($container?->getSlots() ?? [] as $slot) {
            $kind = $slot->getKind();
            if (null !== $kind && $this->registry->has($kind) && !$this->registry->isAllowedInContext($kind, $context)) {
                // Escaped here rather than by Twig: the help text is rendered as HTML (help_html), and a block's own title, which its __toString() appends, is editor-provided content
                $labels[] = htmlspecialchars((string) $slot, ENT_QUOTES, 'UTF-8');
            }
        }

        return $labels;
    }

    // Consumes the "mediaUpload" multi-file input (if any), splicing its files into "medias" - see MultiUploadMerger for the actual entry-building logic
    private function mergeMultiUpload(array $submitted, string $kind): array
    {
        $files = $submitted['mediaUpload'] ?? null;
        unset($submitted['mediaUpload']);
        if (!$this->registry->allowsMultiUpload($kind) || empty($files) || !is_array($files)) {
            return $submitted;
        }

        $submitted['medias'] = MultiUploadMerger::merge($submitted['medias'] ?? [], $files);

        return $submitted;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Block::class,
            'label' => false,
            'translation_domain' => 'ui',
            // Restricts the "kind" choices to block kinds available in this context (see BlockRegistry:: groupedByCategory()) - e.g. 'page' or 'menu'. Null (default) applies no restriction, so existing CollectionField usages that don't pass it keep seeing every pickable kind.
            'context' => null,
            // The language the block is being written in, when it is not the one the site was written in. The same fields are then rendered, filled with what that language says and nothing else, and what comes back goes to the translation table rather than onto the block (see ContentTranslator). Null - every screen until one asks otherwise - is the block itself, exactly as before.
            'translation_locale' => null,
        ]);
        $resolver->setAllowedTypes('context', ['null', 'string']);
        $resolver->setAllowedTypes('translation_locale', ['null', 'string']);
    }
}
