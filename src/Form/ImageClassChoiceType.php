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

// Closed list of classes available in sass/_images.scss, shared by every block form embedding an image
class ImageClassChoiceType extends AbstractType
{
    public const CHOICES = [
        'label.css_class_rounded' => 'img-rounded',
        'label.css_class_thumbnail' => 'img-thumbnail',
        'label.css_class_circle' => 'img-circle',
        'label.css_class_square' => 'img-square',
        'label.css_class_shadow' => 'img-shadow',
        'label.css_class_width_100' => 'width-100',
        'label.css_class_width_125' => 'width-125',
        'label.css_class_width_150' => 'width-150',
        'label.css_class_width_200' => 'width-200',
        'label.css_class_width_250' => 'width-250',
        'label.css_class_width_300' => 'width-300',
        'label.css_class_width_350' => 'width-350',
        'label.css_class_width_400' => 'width-400',
        'label.css_class_width_450' => 'width-450',
        'label.css_class_width_500' => 'width-500',
    ];

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'label.css_classes',
            'help' => 'label.css_classes_help',
            'choices' => self::CHOICES,
            'multiple' => true,
            // Expanded checkboxes were fine for 5 options, too cramped now that width-* brings it to 15
            'expanded' => false,
            'required' => false,
            'translation_domain' => 'ui',
            // Renders as a removable-tags selector (same widget as BookBundle's Serie autocomplete) instead of a plain multi-select
            'attr' => ['data-ea-widget' => 'ea-autocomplete'],
        ]);
    }
}
