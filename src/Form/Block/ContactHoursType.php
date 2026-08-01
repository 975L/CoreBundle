<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use c975L\UiBundle\Service\ContactSnippetBuilder;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// One entry of ContactDetailsType's "hours" collection - one row per time range, not per day, a business closing for
// lunch needing two ranges over the same days ("Mo-Fr 9:00-12:00" then "Mo-Fr 14:00-18:00"). A day no row names is closed.
class ContactHoursType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('days', ChoiceType::class, [
                'label' => 'label.days',
                // schema.org's own day names are stored, the labels alone being translated
                'choices' => array_combine(
                    array_map(static fn (string $day) => 'label.' . strtolower($day), ContactSnippetBuilder::DAYS),
                    ContactSnippetBuilder::DAYS
                ),
                'multiple' => true,
                'expanded' => true,
                'required' => false,
            ])
            // A native time picker rather than free text: the value is stored straight into the block's JSON data,
            // in the very "HH:MM" schema.org publishes, so no typed-in "9h"/"6pm" ever has to be guessed at
            ->add('opens', TimeType::class, $this->timeOptions('label.opens'))
            ->add('closes', TimeType::class, $this->timeOptions('label.closes'));
    }

    private function timeOptions(string $label): array
    {
        return [
            'label' => $label,
            'widget' => 'single_text',
            'input' => 'string',
            'input_format' => 'H:i',
            'with_seconds' => false,
            'required' => false,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'translation_domain' => 'ui',
        ]);
    }
}
