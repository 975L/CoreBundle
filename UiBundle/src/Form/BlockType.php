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
use c975L\UiBundle\Form\Util\CollectionReconciler;
use c975L\UiBundle\Form\Util\MultiUploadMerger;
use c975L\UiBundle\Form\Util\SubmissionIntegrity;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use c975L\UiBundle\Service\VideoPosterImporter;
use Symfony\Component\Form\AbstractType;
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

    public function __construct(
        private readonly BlockRegistry $registry,
        private readonly UrlGeneratorInterface $router,
        private readonly ?RequestStack $requestStack = null,
        private readonly ?VideoPosterImporter $videoPosterImporter = null,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
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
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event) use ($options): void {
                $block = $event->getData();

                CollectionReconciler::addIdField($event->getForm(), $block instanceof Block ? $block->getId() : null);

                $kind = null;
                if (null !== $block) {
                    $kind = is_object($block) ? $block->getKind() : ($block['kind'] ?? null);
                    if ($kind && $this->registry->has($kind)) {
                        $this->addKindField($event->getForm(), $options['context'], $kind);
                        $this->addDataSubForm($event->getForm(), $kind);
                        if ($this->registry->hasMediaTypes($kind)) {
                            $this->addMediaSubForm($event->getForm(), $kind);
                        }
                        if ($this->registry->isContainer($kind)) {
                            $this->addSlotsSubForm($event->getForm(), $kind, $block instanceof Block ? $block : null);
                        }
                    }
                }

                // Added last (after "data", not statically at the top of buildForm) so it always renders below the kind-specific fields (e.g. MenuLinkType's "target") instead of between "kind" and "data"
                $this->addAnimationField($event->getForm());
            }
        );

        // Re-add the sub-form `data` BEFORE Symfony maps submitted values (PRE_SUBMIT), so the correct FormType is in place when the mapping happens.
        $builder->addEventListener(
            FormEvents::PRE_SUBMIT,
            function (FormEvent $event): void {
                $submitted = $event->getData();
                $kind = $submitted['kind'] ?? null;
                if ($kind && $this->registry->has($kind)) {
                    $this->addDataSubForm($event->getForm(), $kind);

                    $block = $event->getForm()->getData();
                    // Picking another kind swaps the "data" sub-form client-side (see block.js) and nothing else: whatever the previous kind rendered around it - the per-image metadata of its medias, its slots - is still in the DOM and still posted. A key no field claims fails the whole form with "This form should not contain extra fields", which is why switching a kind used to take two saves, the browser having re-rendered the form for the new kind on the failed one, losing whatever was typed in between. What the new kind cannot receive is dropped from the submission below, here rather than client-side so a submission that never went through the picker is covered too
                    $previousKind = $block instanceof Block ? $block->getKind() : null;
                    $kindChanged = null !== $previousKind && $previousKind !== $kind;

                    if ($this->registry->hasMediaTypes($kind)) {
                        if ($block instanceof Block) {
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
                    } elseif ($kindChanged) {
                        // The medias themselves are deliberately left on the block rather than removed: the new kind simply doesn't render them, and switching back brings them - their files included - straight back. Which takes both the submitted keys AND the fields PRE_SET_DATA declared for the previous kind: an absent key does not make Symfony skip a declared child, it submits it with null, and CollectionType's resize listener reads that as "every row removed" - cascade-removed and orphan-removed at flush
                        unset($submitted['medias'], $submitted['mediaUpload']);
                        $event->setData($submitted);
                        $event->getForm()->remove('medias');
                        $event->getForm()->remove('mediaUpload');
                    }

                    if ($this->registry->isContainer($kind)) {
                        $this->applySubmittedSlots($event, $kind);
                    } elseif ($kindChanged || isset($submitted[self::SLOTS_RENDERED]) || isset($submitted['slots'])) {
                        // Switched away from a container: its slots collection and the marker beside it are still posted, with no field left to claim either. Taken off the form as well as out of the submission, and the slots kept on the block, for the very same reasons as its medias just above
                        unset($submitted['slots']);
                        $event->setData($submitted);
                        $event->getForm()->remove('slots');

                        // Added back for a kind switched away from a container, which still carries the marker: a key no field claims fails the whole form as an extra one
                        if (isset($submitted[self::SLOTS_RENDERED])) {
                            $this->addSlotsMarker($event->getForm());
                        }
                    }

                    // "data" (and "medias"/"slots") were just (re)added above - move "animation" back below them, in case this is a brand new collection entry whose PRE_SET_DATA fired with no kind yet (so "animation" was added there before "data" ever existed)
                    $event->getForm()->remove('animation');
                    $this->addAnimationField($event->getForm());
                }
            }
        );

        // Here rather than in a Doctrine listener (unlike the nocookie rewrite, see BlockVideoNoCookieListener): the import sets a Vich file field, which has to be in place before Vich's own prePersist/preUpdate listener runs, not alongside it
        // Runs whatever the submission turns out to be worth - this form is always a CollectionType entry, so its POST_SUBMIT precedes the root form's validation. Nothing is flushed here, and a rejected submission's downloaded still is swept on kernel.terminate (see VideoPosterImporter::removeTemporaryFiles)
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (PostSubmitEvent $event): void {
                $block = $event->getData();
                if ($block instanceof Block) {
                    $this->videoPosterImporter?->importIfRequested($block);
                }
            }
        );
    }

    // "slots" absent from the submission means one of two very different things: the editor removed the last slot - an HTML form cannot represent an empty array, only an absent key, same as "medias" - or this form never carried the collection at all. A kind just switched to a container client-side, a body PHP truncated at max_input_vars, a DOM something rewrote: all three arrive as that same absent key. Read as the first, the second deletes every slot, cascaded and orphan-removed, with nothing left to say it ever happened - which is how a live page lost the cards of a section_cards block. The marker is what tells the two apart. The submitted collection itself counts as its own proof: an edit page opened before the marker existed still carries its slots, and must go on saving rather than fail as an extra field. "Neither", and a body PHP cut short, both mean nothing submitted says what the editor removed.
    private function applySubmittedSlots(FormEvent $event, string $kind): void
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

        if ($block instanceof Block) {
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

        $this->addSlotsSubForm($form, $kind, $block instanceof Block ? $block : null);
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
    private function addKindField(FormBuilderInterface | FormInterface $form, ?string $context, ?string $legacyKind = null): void
    {
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

    private function addDataSubForm(FormInterface $form, string $kind): void
    {
        $form->add('data', $this->registry->getFormClass($kind), [
            'label' => false,
            'row_attr' => ['class' => 'block-data-form'],
            // One prefix shared by every kind's own FormType, so the layout below can be themed once for all of them rather than per kind (see form/block_theme.html.twig, "ui_block_data_widget"). Themed rather than written in form/block.html.twig, which only ever renders the fragment the kind picker loads over AJAX - the edit screen itself is rendered by EasyAdmin, which knows nothing of that template
            'block_prefix' => 'ui_block_data',
        ]);
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
    private function addSlotsSubForm(FormInterface $form, string $kind, ?Block $container): void
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
            'entry_options' => ['context' => $slotContext],
            'allow_add' => true,
            'allow_delete' => true,
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
        ]);
        $resolver->setAllowedTypes('context', ['null', 'string']);
    }
}
