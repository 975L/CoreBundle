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

// How much a block's corners are rounded, picked from three fixed steps rather than typed as a length: a free value would put arbitrary CSS in the database and freeze one card's shape against every other one on the page. Each step is a --block-radius-* token (see sass/_tokens.scss), so a site retunes the whole scale from its own theme.css without a single stored value changing - what is stored is a step, not a measurement
class BlockRadiusChoiceType extends AbstractType
{
    public const CHOICES = [
        'label.radius_none' => 'none',
        'label.radius_small' => 'small',
        'label.radius_medium' => 'medium',
        'label.radius_large' => 'large',
    ];

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'label' => 'label.radius',
            'help' => 'label.radius_help',
            'choices' => self::CHOICES,
            // Empty is the placeholder, not a choice: an unset value has to go on meaning "whatever the theme says", which is what every block stored before this field existed holds - and what keeps a site's own themes/ui.css in charge of the shape by default
            'placeholder' => 'label.radius_theme',
            'required' => false,
            'translation_domain' => 'ui',
        ]);
    }
}
