<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Block;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

// Opt-in for any kind FormType whose content is prose an editor may want to set in one of the consuming site's own classes - see UiBundle/README.md "Site CSS classes". Call addCssClassesField() from buildForm(); the stored value is read back by BlockExtension, which wraps the rendered block in a div carrying it, so nothing has to be done in the kind's template.
// Deliberately not offered on every kind: a Slider is a closed composition the bundle styles itself, and BlockClassChoiceType's fixed list is the right field there. This one is the escape hatch for classes only the site knows about (a body size, an accent color), which no list in the bundle could ever hold.
// The Card carries both, and the split is what the two fields answer: the closed list is its shape - radius, shadow, width - which the bundle draws itself, where this one is its palette, a card standing for a scope the consuming site declares (a faction, a brand, a section theme) and redefining the tokens the card reads. A site with two such scopes cannot state the second one anywhere else.
trait HasCssClassesFieldTrait
{
    private function addCssClassesField(FormBuilderInterface $builder): void
    {
        // Free text rather than a choice: the point is the classes the bundle cannot know. What is typed is filtered at render time (see BlockExtension::wrapInCssClasses) rather than validated here, that being the only gate a class also reaching the page through an import goes past
        $builder->add('cssClasses', TextType::class, [
            'label' => 'label.css_classes_free',
            'help' => 'label.css_classes_free_help',
            'required' => false,
        ]);
    }
}
