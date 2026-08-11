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

// The "data" sub-form of the "block_group" container kind - it holds no content of its own, only how its "slots" line up
class BlockGroupType extends AbstractType
{
    // Matches the ".blocks-group--{direction}" modifiers styled in sass/_blocks-group.scss
    public const DIRECTIONS = ['row', 'column'];

    // Same, for ".blocks-group--{justify}"; "center" is the group's own default and writes no class
    public const JUSTIFICATIONS = ['center', 'start', 'end', 'between'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // A group is a line of blocks by default, which is what makes it worth having in a footer: the whole point is to keep several blocks together on one line and push the next group below them
        $builder
            ->add('direction', ChoiceType::class, [
                'label' => 'label.group_direction',
                'help' => 'label.group_direction_help',
                'choices' => array_combine(
                    array_map(static fn (string $direction): string => 'label.group_direction_' . $direction, self::DIRECTIONS),
                    self::DIRECTIONS
                ),
                // No placeholder, same as the other stored-choice fields: an unset value has to keep meaning "row"
                'placeholder' => false,
            ])
            ->add('justify', ChoiceType::class, [
                'label' => 'label.group_justify',
                'choices' => array_combine(
                    array_map(static fn (string $justify): string => 'label.group_justify_' . $justify, self::JUSTIFICATIONS),
                    self::JUSTIFICATIONS
                ),
                'placeholder' => false,
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
