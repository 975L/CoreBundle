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
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SlideType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('text', TrixEditorType::class, [
                'label' => 'label.content',
                'required' => false,
            ])
            // TextType and not UrlType, for the reason given in MediaUploadType: a slide links to a page of this very site as often as elsewhere, and an <input type="url"> refuses anything but an absolute url - blocking the whole form client-side, silently
            ->add('url', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ])
            ->add('credits', TextType::class, [
                'label' => 'label.credits',
                'required' => false,
            ])
            ->add('rightsReserved', CheckboxType::class, [
                'label' => 'label.rights_reserved',
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
