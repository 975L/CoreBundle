<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\BlockAccentChoiceType;
use c975L\UiBundle\Form\BlockClassChoiceType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CardType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', TextType::class, [
                'label' => 'label.identifier',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('level', ChoiceType::class, [
                'label' => 'label.title_level',
                'choices' => [
                    'h2' => 'h2',
                    'h3' => 'h3',
                    'h4' => 'h4',
                ],
            ])
            ->add('content', TrixEditorType::class, [
                'label' => 'label.content',
            ])
            // Optional teaser fields: when a media (see media_types on this block's tag) and/or a url is set, blocks/Card.html.twig renders an image + button teaser instead of the plain content box - several such cards placed next to each other on a page (e.g. a "our sites" listing) are auto-wrapped in a ".cards" flex row by templates/components/Blocks/Blocks.html.twig
            ->add('url', TextType::class, [
                'label' => 'label.url',
                'required' => false,
            ])
            ->add('target', ChoiceType::class, [
                'label' => 'label.target',
                'required' => false,
                'choices' => [
                    'label.same_window' => '',
                    'label.new_tab' => '_blank',
                ],
            ])
            ->add('buttonLabel', TextType::class, [
                'label' => 'label.button_label',
                'help' => 'label.button_label_help',
                'required' => false,
            ])
            ->add('class', BlockClassChoiceType::class)
            ->add('accent', BlockAccentChoiceType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
