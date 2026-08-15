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

// How many of a card fit one row, picked from three fixed steps rather than typed as a width: six compact, three by default, two big. Each step is a --card-width-* token (see sass/_tokens.scss), computed off the measure the page declares, so a site framing its content tighter keeps its row whole - which a stored length could never do. What is stored is a count, not a measurement
// Separate from BlockClassChoiceType, which holds the fixed pixel widths: that one answers "how wide is this block", this one "how many of it to the row", and the row is what makes the answer right. The two were one field until the widths were told apart from the classes
class BlockCardSizeChoiceType extends AbstractType
{
    public const CHOICES = [
        'label.card_size_compact' => 'compact',
        'label.card_size_big' => 'big',
    ];

    #[\Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'label.card_size',
            'help' => 'label.card_size_help',
            'choices' => self::CHOICES,
            // Empty is the placeholder and not a choice of its own, same as the radius and shadow fields: the default row of three is what every card stored before this field existed holds, and it must go on holding it
            'placeholder' => 'label.card_size_default',
            'required' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
