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
