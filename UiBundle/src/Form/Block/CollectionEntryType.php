<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Registry\CollectionSourceRegistry;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The singular of CollectionType: one item of a source instead of a listing of them - a book put forward on a home page, the latest release of a series. Nothing is entered here either, the item being picked out of the source rather than written down, which is what keeps the page in step with the entities behind it
class CollectionEntryType extends AbstractType
{
    use HasAnchorFieldTrait;

    // Which of a source's items the block shows. "first" and "last" read the source's own order - a source ordering by date puts its latest at one end - where "slug" names one item and holds whatever that source may reorder afterwards
    public const PICKS = ['first', 'last', 'slug'];

    public function __construct(
        private readonly CollectionSourceRegistry $sourceRegistry,
        private readonly BlockAnchorSlugger $anchorSlugger,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $choices = $this->sourceRegistry->choices();

        $builder
            ->add('source', ChoiceType::class, [
                'label' => 'label.source',
                'choices' => $choices,
                // Same as CollectionType: an empty select on a fresh install says why it is empty rather than leaving the editor guessing
                'placeholder' => [] === $choices ? 'label.no_collection_source_available' : null,
            ])
            ->add('pick', ChoiceType::class, [
                'label' => 'label.collection_entry_pick',
                'help' => 'label.collection_entry_pick_help',
                'choices' => array_combine(
                    array_map(static fn (string $pick): string => 'label.collection_entry_pick_' . $pick, self::PICKS),
                    self::PICKS
                ),
                // No placeholder, same as every other stored-choice field: an unset value has to keep meaning "first"
                'placeholder' => false,
            ])
            ->add('slug', TextType::class, [
                'label' => 'label.slug',
                'help' => 'label.collection_entry_slug_help',
                'required' => false,
            ])
            ->add('eyebrow', TextType::class, [
                'label' => 'label.eyebrow',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            // Same field as CollectionType's, and for the same reason: only used to decide whether the item's own title links to its detail URL
            ->add('detailPage', TextType::class, [
                'label' => 'label.detail_page',
                'help' => 'label.detail_page_help',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }

    // Same reason as CollectionType's own: the prefix derived from the class name would collide with Symfony's CollectionType inside PageCrudController's "blocks" field
    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'block_collection_entry';
    }
}
