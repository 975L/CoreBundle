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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FaqType extends AbstractType
{
    use HasAnchorFieldTrait;

    public function __construct(private readonly BlockAnchorSlugger $anchorSlugger)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->addAnchorField($builder, $this->anchorSlugger);

        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('items', CollectionType::class, [
                'label' => 'label.questions',
                'entry_type' => FaqItemType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            // The first answer already unfolded, for a page whose first question is the one everybody asks
            ->add('openFirst', CheckboxType::class, [
                'label' => 'label.faq_open_first',
                'required' => false,
            ])
            // Two columns halve a long list on a wide screen, at the cost of the structured data: Google reads a FAQPage as one ordered list, so it is only published when the questions are laid in one
            ->add('columns', ChoiceType::class, [
                'label' => 'label.columns',
                'choices' => ['1' => 1, '2' => 2],
                'required' => false,
                'placeholder' => false,
                'choice_translation_domain' => false,
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
