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
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ImageCompareType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // Auto-generated unique HTML id (used for the section id/data-image-compare-id), not user-editable - same rationale as SliderType's own "id" field
            ->add('id', HiddenType::class)
            ->add('startPosition', IntegerType::class, [
                'label' => 'label.start_position',
                'help' => 'label.start_position_help',
            ])
            ->add('beforeLabel', TextType::class, [
                'label' => 'label.before_label',
                'required' => false,
            ])
            ->add('afterLabel', TextType::class, [
                'label' => 'label.after_label',
                'required' => false,
            ])
            ->add('class', BlockClassChoiceType::class);

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (PreSetDataEvent $event): void {
                $data = $event->getData();
                if (!is_array($data)) {
                    $data = [];
                }

                // Defaults for a brand new comparator only - see SliderType for why these aren't set via each field's own "data" option instead
                if (!isset($data['id']) || '' === $data['id']) {
                    $data['id'] = BlockIdGenerator::generate('image-compare');
                }
                $data['startPosition'] ??= 50;
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
