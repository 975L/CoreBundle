<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Form\BlockClassChoiceType;
use c975L\UiBundle\Form\TrixEditorType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The video file and its optional cover image are uploaded as block medias (see the "ui.block.video" tag in config/services.yaml), so this form only carries the player's own display options - neither the file path nor the format is asked for anymore, blocks/Video.html.twig reads both back from the uploaded Media itself (its stored mimeType). The title/description/width/height/class fields are deliberately the same as VideoIframeType's, both kinds rendering the same <figure> structure
class VideoType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // A single multi-select (same removable-tags widget as BlockClassChoiceType) rather than three separate checkboxes
            ->add('options', ChoiceType::class, [
                'label' => 'label.video_options',
                'help' => 'label.video_options_help',
                'choices' => [
                    'label.autoplay' => 'autoplay',
                    'label.muted' => 'muted',
                    'label.loop' => 'loop',
                ],
                'multiple' => true,
                'expanded' => false,
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('description', TrixEditorType::class, [
                'label' => 'label.description',
                'required' => false,
            ])
            ->add('width', TextType::class, [
                'label' => 'label.width',
                'required' => false,
            ])
            ->add('height', TextType::class, [
                'label' => 'label.height',
                'required' => false,
            ])
            ->add('class', BlockClassChoiceType::class);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
