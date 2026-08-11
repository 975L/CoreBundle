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

// The accent color a block can be marked with, picked from twelve fixed hues rather than from a color input: a free value would put arbitrary CSS in the database, and a per-site vocabulary ("socle", "sale"...) would tie a look to a subject that changes. A hue names itself and stays true whatever the cards end up being about. Each one is a --block-accent-* token (see sass/_tokens.scss), so a site retunes any of them from its own theme.css without the stored value ever changing
class BlockAccentChoiceType extends AbstractType
{
    // Order follows the color wheel, greys last - it is the order the picker shows
    public const CHOICES = [
        'label.accent_red' => 'red',
        'label.accent_orange' => 'orange',
        'label.accent_yellow' => 'yellow',
        'label.accent_lime' => 'lime',
        'label.accent_green' => 'green',
        'label.accent_teal' => 'teal',
        'label.accent_cyan' => 'cyan',
        'label.accent_blue' => 'blue',
        'label.accent_indigo' => 'indigo',
        'label.accent_violet' => 'violet',
        'label.accent_pink' => 'pink',
        'label.accent_grey' => 'grey',
    ];

    #[\Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'label.accent',
            'help' => 'label.accent_help',
            'choices' => self::CHOICES,
            // Empty is the placeholder, not a choice: an unset value must keep meaning "no accent", which is what every block stored before this field existed holds
            'placeholder' => 'label.accent_none',
            'required' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
