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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Each item comes from the chosen source, rendered as a never-persisted "collection_item" Block - see Collection.html.twig. No item is entered here: source/limit only pick which external collection to pull from and how many of its items to show
class CollectionType extends AbstractType
{
    use HasAnchorFieldTrait;

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
                // No bundle implementing CollectionSourceProviderInterface yet (fresh install) means an empty select with nothing to pick - a disabled placeholder explains why instead of leaving the editor facing a blank dropdown with no clue what's wrong
                'placeholder' => [] === $choices ? 'label.no_collection_source_available' : null,
            ])
            ->add('limit', IntegerType::class, [
                'label' => 'label.limit',
                'help' => 'label.collection_limit_help',
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
            // "Voir tout" link next to the head, e.g. pointing at a fuller listing elsewhere - same pair of fields as PortfolioGridType, only rendered when the "portfolio" variant is picked (see Collection/Grid.html.twig)
            ->add('linkLabel', TextType::class, [
                'label' => 'label.link_label',
                'required' => false,
            ])
            ->add('linkUrl', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ])
            // Slug of a real Page (site_page) that renders this source's per-item detail views - its own blocks are rendered as-is (a "collectionItem" Twig global carries the current item's data, picked up by whichever block on it needs it, e.g. a "twig_content" block's own templatePath) - see PageController::resolveCollectionDetail() and SiteBundle's README ("Item detail pages", under "Collection entries"). Never rendered by this block itself - only used to decide whether each item's own title links to its detail URL (see CollectionExtension::renderItems()), for items whose source also hands back a slug.
            ->add('detailPage', TextType::class, [
                'label' => 'label.detail_page',
                'help' => 'label.detail_page_help',
                'required' => false,
            ])
            // Picked up by CollectionItem.html.twig to switch its markup - keeps every collection sharing the same "collection_item" kind/template (see class-level comment) while still allowing a visually different presentation per Collection block instance, no app-level template override needed
            ->add('variant', ChoiceType::class, [
                'label' => 'label.variant',
                'help' => 'label.variant_help',
                'required' => false,
                'choices' => [
                    'label.variant_card' => '',
                    'label.variant_portfolio' => 'portfolio',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }

    // Without this, the default block prefix derived from the class name ("CollectionType" -> "collection") collides with Symfony's own CollectionType (used by PageCrudController's "blocks" field), making EasyAdmin's collection_row/collection_widget form theme blocks wrongly apply here and blow up on "allow_add"/"allow_delete" - vars only a real CollectionType field populates
    public function getBlockPrefix(): string
    {
        return 'block_collection';
    }
}
