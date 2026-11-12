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
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class ProgressBarType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'label.title',
                'required' => false,
            ])
            ->add('progressPercent', IntegerType::class, [
                'label' => 'label.progress_percent',
                'attr' => [
                    'min' => 0,
                    'max' => 100,
                ],
                'constraints' => [new Range(min: 0, max: 100)],
            ])
            ->add('text', CheckboxType::class, [
                'label' => 'label.show_percent',
                'required' => false,
            ]);

        // "Show the percentage" ticked for a new bar only: the field's own "data" option would tick it again on every save, discarding a bar switched off
        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            static function (PreSetDataEvent $event): void {
                $data = $event->getData();
                $data = is_array($data) ? $data : [];
                $data['text'] ??= true;
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
