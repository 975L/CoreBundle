<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The "data" sub-form of the "favorite_link" kind - there is no target to pick, the block only ever points at this bundle's own wishlist page, so its wording is the one thing left to say
class FavoriteLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Overrides this bundle's own wording ("Ma liste"), a site preferring "Mes envies" or naming the list after what it sells
        $builder->add('label', TextType::class, [
            'label' => 'label.favorite_link_label',
            'help' => 'label.favorite_link_label_help',
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
