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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BannerTitleType extends AbstractType
{
    // How tall the banner stands, on three fixed steps rather than the free pixel value this field used to take - the same reasoning as the corners and the shadow of a block (see BlockRadiusChoiceType): a length typed here is arbitrary CSS stored against the block, which no class can carry and which a nonce-based style-src then forces into a <style> element, invalid anywhere but the <head>.
    // Each step is a --banner-title-height-* token a site retunes from its own theme.css, so the value stored against a banner never changes with it. A floor rather than the cap this field was: a step is the height an editor asks the banner to stand at, and a title long enough to need more room gets it instead of being cropped
    public const HEIGHT_CHOICES = [
        'label.banner_height_small' => 'small',
        'label.banner_height_medium' => 'medium',
        'label.banner_height_large' => 'large',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'label.title',
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'label.title_level',
                'choices' => [
                    'h1' => 'h1',
                    'h2' => 'h2',
                    'h3' => 'h3',
                ],
            ])
            ->add('height', ChoiceType::class, [
                'label' => 'label.height',
                'help' => 'label.banner_height_help',
                'choices' => self::HEIGHT_CHOICES,
                // Empty is the placeholder, not a choice: an unset value keeps the floor the stylesheet sets, which is what every banner rendered before this field existed already stood at
                'placeholder' => 'label.banner_height_auto',
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
