<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;

// The "data" sub-form of the "flex_columns" container kind - see AbstractSectionHeadContainerType
class FlexColumnsType extends AbstractSectionHeadContainerType
{
    // Matches the ".flex-columns--{alignment}" modifiers styled in sass/_page-sections.scss; "top" is the row's own default and writes no class, so every row stored before this field existed goes on rendering as it did
    public const VERTICAL_ALIGNMENTS = ['top', 'middle', 'bottom'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // Columns of unequal height hang from the top of the row by default, which is what a row of cards or of stacked sections wants. A row pairing one long column with one short one - a lead and the paragraph answering it - reads as one statement instead, and the two have to be set against each other's middle
        $builder->add('verticalAlign', ChoiceType::class, [
            'label' => 'label.vertical_align',
            'help' => 'label.vertical_align_help',
            'choices' => array_combine(
                array_map(static fn (string $alignment): string => 'label.vertical_align_' . $alignment, self::VERTICAL_ALIGNMENTS),
                self::VERTICAL_ALIGNMENTS
            ),
            // No placeholder, same as the other stored-choice fields: an unset value has to keep meaning "top"
            'placeholder' => false,
        ]);
    }
}
