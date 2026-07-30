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
use c975L\UiBundle\Registry\BlockRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Constraints\Count;

// Check Readme for usage instructions
class BlockType extends AbstractType
{
    // "hero"'s pure-CSS crossfade slideshow only has :nth-child/[data-count] rules for up to this many images (see .hero__media--slideshow in sass/_page-sections.scss) - beyond it, extra images would silently collide with an earlier slide's animation timing instead of taking their own turn
    private const HERO_MEDIA_MAX = 6;

    public function __construct(
        private BlockRegistry $registry,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addKindField($builder, $options['context']);

        $builder->add('position', HiddenType::class, [
            'attr' => ['class' => 'ui-sort-position'],
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
                    if ($this->registry->hasMediaTypes($kind)) {
                        $block = $event->getForm()->getData();
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
                        if (!isset($submitted['medias'])) {
                            $submitted['medias'] = [];
                        }
                        $submitted = $this->mergeMultiUpload($submitted, $kind);
                        $event->setData($submitted);
                        $this->addMediaSubForm($event->getForm(), $kind);
                    }

                    if ($this->registry->isContainer($kind)) {
                        $block = $event->getForm()->getData();
                        if ($block instanceof Block) {
                            CollectionReconciler::pruneRemoved(
                                $block->getSlots(),
                                $submitted['slots'] ?? [],
                                static fn (Block $slot) => $block->removeSlot($slot)
                            );
                        }

                        // Same reasoning as "medias" above: removing the last slot leaves the key entirely absent from the submission
                        if (!isset($submitted['slots'])) {
                            $submitted['slots'] = [];
                            $event->setData($submitted);
                        }
                        $this->addSlotsSubForm($event->getForm(), $kind, $block instanceof Block ? $block : null);
                    }

                    // "data" (and "medias"/"slots") were just (re)added above - move "animation" back below them, in case this is a brand new collection entry whose PRE_SET_DATA fired with no kind yet (so "animation" was added there before "data" ever existed)
                    $event->getForm()->remove('animation');
                    $this->addAnimationField($event->getForm());
                }
            }
        );
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
                'data-ea-widget' => 'ea-autocomplete',
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
                'data-block-collection' => '1',
                'data-block-container-id' => $containerId,
            ] : [],
        ]);
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
