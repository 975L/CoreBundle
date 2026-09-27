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
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

// A comparison at a glance: what is compared down the first column, one column per offer, one of them set apart, each cell a tick, a cross or a few words
class ComparisonTableType extends AbstractType
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
            // The head of the first column, over what is compared
            ->add('firstColumn', TextType::class, [
                'label' => 'label.first_column',
                'required' => false,
            ])
            ->add('columns', CollectionType::class, [
                'label' => 'label.columns',
                'entry_type' => ComparisonColumnType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            // The column set apart, counted from 1 over the offers - the site's own, usually
            ->add('highlight', IntegerType::class, [
                'label' => 'label.highlight',
                'help' => 'help.comparison_highlight',
                'required' => false,
                'constraints' => [new Range(min: 1)],
            ])
            ->add('rows', CollectionType::class, [
                'label' => 'label.rows',
                'entry_type' => ComparisonRowType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
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
