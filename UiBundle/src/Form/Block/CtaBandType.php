<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\TrixEditorType;
use c975L\UiBundle\Service\BlockAnchorSlugger;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CtaBandType extends AbstractType
{
    use HasCssClassesFieldTrait;

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
            ])
            ->add('text', TrixEditorType::class, [
                'label' => 'label.text',
                'required' => false,
            ])
            ->add('ctaLabel', TextType::class, [
                'label' => 'label.cta_label',
            ])
            ->add('ctaUrl', TextType::class, [
                'label' => 'label.url',
            ]);

        // A band sending the visitor to the other side of a site reads better in that side's own colors than in the ones around it - a scope class the site declares, which no list in the bundle could hold
        $this->addCssClassesField($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
