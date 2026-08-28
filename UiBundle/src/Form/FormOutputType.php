<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form;

use c975L\UiBundle\Entity\FormOutput;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSetDataEvent;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;

// Entry type for the "outputs" CollectionField of a Form (see FormCrudController), sortable by the admin-wide ea-sortable.js like the fields above it - and sortable for a reason the fields don't have: an expression only sees the outputs placed before it, so moving a row changes what it can read
class FormOutputType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'label' => 'label.output_label',
                // The column's own width, like the expression below - refused here rather than truncated on the way to the database
                'constraints' => [new Length(max: 100)],
            ])
            // Validated on save against this Form's own variables (see ValidExpressionsValidator), never at render time - a formula that can't be evaluated is refused on the screen it was typed on
            ->add('expression', TextType::class, [
                'label' => 'label.output_expression',
                'help' => 'label.output_expression_help',
                // The column's own width - a formula longer than that is refused here rather than truncated on the way to the database
                'constraints' => [new Length(max: 500)],
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'label.output_format',
                'choices' => self::formatChoices(),
            ])
            ->add('decimals', IntegerType::class, [
                'label' => 'label.output_decimals',
                'required' => false,
            ])
            ->add('unit', TextType::class, [
                'label' => 'label.output_unit',
                'required' => false,
                'help' => 'label.output_unit_help',
                'constraints' => [new Length(max: 20)],
            ])
            ->add('visible', CheckboxType::class, [
                'label' => 'label.output_visible',
                'required' => false,
                'help' => 'label.output_visible_help',
            ])
            ->add('highlighted', CheckboxType::class, [
                'label' => 'label.output_highlighted',
                'required' => false,
            ])
            ->add('position', HiddenType::class, [
                'attr' => ['class' => 'ui-sort-position'],
            ])
        ;

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            static function (PreSetDataEvent $event): void {
                $output = $event->getData();

                CollectionReconciler::addIdField($event->getForm(), $output instanceof FormOutput ? $output->getId() : null);
            }
        );
    }

    /** @return array<string, string> */
    public static function formatChoices(): array
    {
        return [
            'label.output_format_number' => FormOutput::FORMAT_NUMBER,
            'label.output_format_currency' => FormOutput::FORMAT_CURRENCY,
            'label.output_format_percent' => FormOutput::FORMAT_PERCENT,
        ];
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FormOutput::class,
            'label' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
