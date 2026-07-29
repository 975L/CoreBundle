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

// Opt-in for any full-width section kind FormType wanting to be paintable as a colored flat - see UiBundle/README.md "Colored backgrounds". Call addBackgroundField() from buildForm(); the stored value ends up on the component as its "background" prop, which turns it into a .section--bg-* class (see sass/_page-sections.scss). Nothing else is needed: the three variants only redefine custom properties every section rule already reads, so a kind supports the option as soon as its own component passes the prop through.
trait HasBackgroundFieldTrait
{
    private function addBackgroundField(FormBuilderInterface $builder): void
    {
        // Empty is the placeholder, not a choice: an unset value must keep meaning "no flat"
        $builder->add('background', ChoiceType::class, [
            'label' => 'label.section_background',
            'help' => 'label.section_background_help',
            'choices' => [
                'label.section_background_muted' => 'muted',
                'label.section_background_primary' => 'primary',
                'label.section_background_dark' => 'dark',
            ],
            'placeholder' => 'label.section_background_none',
            'required' => false,
        ]);
    }
}
