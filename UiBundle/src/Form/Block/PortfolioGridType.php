<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Each project card itself comes from this block's medias (see MediaUploadType's "portfolio_grid" context: label = project title, description = project text, url = outbound link)
class PortfolioGridType extends AbstractType
{
    use HasAnchorFieldTrait;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $builder
            ->add('eyebrow', TextType::class, [
                'label' => 'label.eyebrow',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            // Same field, same reading and same automatic default as CollectionType's: the block's head is the <h2> of the section it opens, and each project's title hangs under it - <h3> when there is a head, <h2> when the grid stands on its projects alone, straight under the page's <h1>
            ->add('level', ChoiceType::class, [
                'label' => 'label.collection_item_level',
                'help' => 'label.collection_item_level_help',
                'required' => false,
                'choices' => [
                    'label.collection_item_level_auto' => '',
                    'h2' => 'h2',
                    'h3' => 'h3',
                    'h4' => 'h4',
                ],
                // No placeholder: the stored empty value is the automatic choice itself, not the absence of one
                'placeholder' => false,
            ])
            ->add('linkLabel', TextType::class, [
                'label' => 'label.link_label',
                'required' => false,
            ])
            ->add('linkUrl', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ])
            // Picked up by the component to switch its markup, the same field CollectionType offers - a grid of projects reads as cards, a grid of pictures put forward for themselves (covers, posters, the cards of a game) reads better without: no ground, no border, and the ratio each file was uploaded in rather than the 16/10 a screenshot wants
            ->add('variant', ChoiceType::class, [
                'label' => 'label.variant',
                'required' => false,
                'choices' => [
                    'label.variant_card' => '',
                    'label.variant_plain' => 'plain',
                    'label.variant_thumbnail' => 'thumbnail',
                ],
                // No placeholder, same reading as the level field above: the stored empty value is itself a choice - the card - and not the absence of one
                'placeholder' => false,
            ])
            // Off by default: a grid already published goes on rendering exactly as it did, and a project pointing somewhere keeps its link either way (the component's own rule, see components/Portfolio/Grid.html.twig)
            ->add('zoom', CheckboxType::class, [
                'label' => 'label.image_zoom',
                'help' => 'label.image_zoom_help',
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
}
