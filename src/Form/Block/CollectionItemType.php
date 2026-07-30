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
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The "collection_item" kind is never picked manually (see its "pickable: false" tag) - this form only documents the data contract CollectionExtension::renderItems() actually fills: the 6 fields a CollectionItem can carry, plus "detailUrl", the item's detail page link it computes from the item's own slug (see CollectionItem.html.twig)
class CollectionItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.content',
                'required' => false,
            ])
            ->add('url', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ])
            ->add('imageUrl', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ])
            ->add('buttonLabel', TextType::class, [
                'label' => 'label.button_label',
                'required' => false,
            ])
            ->add('buttonIcon', TextType::class, [
                'label' => 'label.identifier',
                'required' => false,
            ])
            ->add('detailUrl', TextType::class, [
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
