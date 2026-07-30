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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RichSnippetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('itemscope', ChoiceType::class, [
                'label' => 'label.schema_type',
                'choices' => [
                    'LocalBusiness' => 'https://schema.org/LocalBusiness',
                    'AutoRepair' => 'https://schema.org/AutoRepair',
                    'Organization' => 'https://schema.org/Organization',
                ],
            ])
            ->add('name', TextType::class, [
                'label' => 'label.name',
            ])
            ->add('description', TrixEditorType::class, [
                'label' => 'label.description',
            ])
            ->add('telephone', TextType::class, [
                'label' => 'label.phone',
                'required' => false,
            ])
            ->add('image', TextType::class, [
                'label' => 'label.image',
                'required' => false,
            ])
            ->add('openingHours', TextType::class, [
                'label' => 'label.opening_hours',
                'required' => false,
            ])
            ->add('priceRange', TextType::class, [
                'label' => 'label.price_range',
                'required' => false,
            ])
            ->add('addressStreetAddress', TextType::class, [
                'label' => 'label.street_address',
                'required' => false,
            ])
            ->add('addressPostalCode', TextType::class, [
                'label' => 'label.postal_code',
                'required' => false,
            ])
            ->add('addressAddressLocality', TextType::class, [
                'label' => 'label.city',
                'required' => false,
            ])
            ->add('addressAddressCountryCode', TextType::class, [
                'label' => 'label.country_code',
                'required' => false,
            ])
            ->add('addressAddressCountryName', TextType::class, [
                'label' => 'label.country',
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
