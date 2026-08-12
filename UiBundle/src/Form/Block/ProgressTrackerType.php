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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// The segmented sibling of ProgressBarType: where a bar reads a percentage off a continuous track, this one counts discrete things obtained out of a known total - volumes published, episodes released, milestones passed
class ProgressTrackerType extends AbstractType
{
    // Past a few dozen the segments are thinner than the gaps parting them and the row stops being readable, so the count is what carries the information - the template clamps to the same ceiling, a fixture or an import reaching it without passing through this form
    public const int MAX_SEGMENTS = 60;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('eyebrow', TextType::class, [
                'label' => 'label.eyebrow',
                'required' => false,
            ])
            ->add('title', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('total', IntegerType::class, [
                'label' => 'label.total',
                'attr' => [
                    'min' => 1,
                    'max' => self::MAX_SEGMENTS,
                ],
            ])
            ->add('completed', IntegerType::class, [
                'label' => 'label.completed',
                'attr' => [
                    'min' => 0,
                    'max' => self::MAX_SEGMENTS,
                ],
            ])
            ->add('note', TextareaType::class, [
                'label' => 'label.note',
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
