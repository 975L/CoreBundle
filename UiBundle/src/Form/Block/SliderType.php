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
use c975L\UiBundle\Form\Util\BlockIdGenerator;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SliderType extends AbstractType
{
    // Values double as the CSS modifier suffix (slider-ratio-{value}), except "free" which applies none
    public const RATIO_CHOICES = [
        'label.ratio_free' => 'free',
        'label.ratio_2_3' => '2-3',
        'label.ratio_3_4' => '3-4',
        'label.ratio_9_16' => '9-16',
        'label.ratio_1_1' => '1-1',
        'label.ratio_3_2' => '3-2',
        'label.ratio_4_3' => '4-3',
        'label.ratio_16_9' => '16-9',
        'label.ratio_21_9' => '21-9',
    ];

    // Values match the Slider component's layout="" param (see templates/components/Slider/Slider.html.twig)
    public const LAYOUT_CHOICES = [
        'label.layout_default' => 'default',
        'label.layout_freeflow' => 'freeflow',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Auto-generated unique HTML id (used for the section id/data-slider-id), not user-editable: avoids collisions/typos that a free-text identifier would allow, see PRE_SET_DATA below
            ->add('id', HiddenType::class)
            ->add('duration', IntegerType::class, [
                'label' => 'label.slide_duration',
            ])
            ->add('ratio', ChoiceType::class, [
                'label' => 'label.ratio',
                'help' => 'label.ratio_help',
                'choices' => self::RATIO_CHOICES,
            ])
            ->add('layout', ChoiceType::class, [
                'label' => 'label.layout',
                'help' => 'label.layout_help',
                'choices' => self::LAYOUT_CHOICES,
            ])
            ->add('class', BlockClassChoiceType::class);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $data = $event->getData();
                if (!is_array($data)) {
                    $data = [];
                }

                // Defaults for a brand new slider only - setting these via the field's own "data" option instead would force this value on every render, silently discarding the saved value of an existing slider (Symfony's "data" option always overrides the underlying model data)
                if (!isset($data['id']) || '' === $data['id']) {
                    $data['id'] = BlockIdGenerator::generate('slider');
                }
                $data['duration'] ??= 0;
                $data['ratio'] ??= 'free';
                $data['layout'] ??= 'default';
                $event->setData($data);
            }
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
