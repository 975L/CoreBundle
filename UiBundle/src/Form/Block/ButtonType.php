<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\IconPickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ButtonType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'label.content',
                'required' => true,
            ])
            ->add('url', TextType::class, [
                'label' => 'label.url',
                'required' => true,
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'label.style',
                'required' => true,
                'choices' => [
                    'label.primary' => 'primary',
                    'label.secondary' => 'secondary',
                    'label.success' => 'success',
                    'label.danger' => 'danger',
                    'label.link' => 'link',
                ],
            ])
            ->add('target', ChoiceType::class, [
                'label' => 'label.target',
                'required' => false,
                'choices' => [
                    'label.same_window' => '',
                    'label.new_tab' => '_blank',
                ],
            ])
            ->add('icon', IconPickerType::class, [
                'label' => 'label.icon',
                'required' => false,
            ])
            ->add('download', CheckboxType::class, [
                'label' => 'label.force_download',
                'required' => false,
            ])
            ->add('inline', CheckboxType::class, [
                'label' => 'label.inline',
                'required' => false,
            ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'ui_button';
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
