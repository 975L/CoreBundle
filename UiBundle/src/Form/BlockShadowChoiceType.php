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

// How far a block is lifted off the page, on the same three fixed steps BlockRadiusChoiceType offers and for the same reasons - each one a --block-shadow-* token a site retunes from its own theme.css.
// What "theme" means is the kind's own business, not this list's: a card carries no shadow at all until one is picked here, where a flip card has always been shadowed and this retunes the shadow it already had (see sass/_cards.scss and sass/_flip-card.scss)
class BlockShadowChoiceType extends AbstractType
{
    public const CHOICES = [
        'label.shadow_none' => 'none',
        'label.shadow_small' => 'small',
        'label.shadow_medium' => 'medium',
        'label.shadow_large' => 'large',
    ];

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'label.shadow',
            'help' => 'label.shadow_help',
            'choices' => self::CHOICES,
            // Empty is the placeholder, not a choice - same rule as the radius above: an unset value keeps the look every block stored before this field existed already had
            'placeholder' => 'label.shadow_theme',
            'required' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
