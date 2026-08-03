<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\IconPickerType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// One entry of SectionFeaturesType's "cards" collection
class SectionFeatureItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Reuses the existing icon picker (see ButtonType) instead of a per-card media upload - keeps this kind free of the block-level media wiring entirely
            ->add('icon', IconPickerType::class, [
                'label' => 'label.icon',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
            ])
            ->add('text', TrixEditorType::class, [
                'label' => 'label.text',
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
