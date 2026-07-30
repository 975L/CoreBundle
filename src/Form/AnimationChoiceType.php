<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;

// Entrance animation played once a Block scrolls into view (assets/js/animate-scroll.js), matching the CSS classes defined in sass/_animations-classes.scss. Single choice: a block gets one entrance effect, not a stack of them. Only the one-shot classes (animation-fill-mode: both, no "infinite") belong here - bounce-* and rotate-x/y* loop forever and are meant for hover/attention effects elsewhere, not entrances.
class AnimationChoiceType extends AbstractType
{
    public const CHOICES = [
        'label.animation_fade_in' => 'fade-in',
        'label.animation_fade_in_bottom' => 'fade-in-bottom',
        'label.animation_fade_in_top' => 'fade-in-top',
        'label.animation_fade_in_left' => 'fade-in-left',
        'label.animation_fade_in_right' => 'fade-in-right',
        'label.animation_slide_in_left' => 'slide-in-left',
        'label.animation_slide_in_right' => 'slide-in-right',
        'label.animation_zoom_in' => 'zoom-in',
    ];

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'label.animation',
            'help' => 'label.animation_help',
            'choices' => self::CHOICES,
            'multiple' => false,
            'expanded' => false,
            'required' => false,
            'placeholder' => 'label.animation_none',
            'translation_domain' => 'ui',
            'attr' => ['data-ea-widget' => 'ea-autocomplete'],
        ]);
    }
}
