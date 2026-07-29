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
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// A column holds no content, only its width and its "slots"; a bare block used as a slot has nowhere to store that width
class FlexColumnType extends AbstractType
{
    // Twelfths, which divide by 2, 3, 4 and 6, so an editor can add a row up in their head
    public const WIDTHS = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Empty is the placeholder, not a choice: an unset value must keep meaning "no width of my own"
        $builder->add('columnWidth', ChoiceType::class, [
            'label' => 'label.column_width',
            'help' => 'label.column_width_help',
            'choices' => array_combine(
                array_map(static fn (string $width): string => 'label.column_width_' . $width, self::WIDTHS),
                self::WIDTHS
            ),
            'placeholder' => 'label.column_width_auto',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'         => null,
            'translation_domain' => 'ui',
        ]);
    }
}
