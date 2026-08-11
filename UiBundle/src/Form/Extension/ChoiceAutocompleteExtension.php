<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\ChoiceList\View\ChoiceGroupView;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

// Decides, for every choice field of every bundle, whether it renders as a plain <select> or as EasyAdmin's searchable TomSelect widget - the rule being the size of the list, never which bundle declared the field. Written once here rather than as a "data-ea-widget" attribute repeated field by field: an attribute nobody remembers to add is what made two neighbouring selects of the same screen behave differently, one filtering as you type and the other not. Extending ChoiceType covers EntityType and EasyAdmin's own ChoiceField/AssociationField too, all three being built on it. The attribute is inert outside the admin, where no script reads it.
// It therefore *overrides* EasyAdmin, which hands the widget to every non-expanded ChoiceField and AssociationField there is: this is the one place deciding, so a field cannot opt back into a search box below the threshold. Nothing is lost by that - a list short enough to read at a glance is faster as a native select, and it keeps its empty option selectable. What genuinely cannot be counted is the one case still exempt: a list fed by an endpoint rather than by its own options.
class ChoiceAutocompleteExtension extends AbstractTypeExtension
{
    // Below this many options a native <select> is faster to use than a search box, and it keeps its empty "none" option selectable - TomSelect drops that one from the list (allowEmptyOption), leaving the clear button as the only way back to no value
    public const AUTOCOMPLETE_THRESHOLD = 10;

    public static function getExtendedTypes(): iterable
    {
        return [ChoiceType::class];
    }

    // Applied on the view rather than on the options: a choice list loaded lazily (EntityType, any choice_loader) only knows how long it is once built
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $attr = $view->vars['attr'] ?? [];

        // Radios and checkboxes are a layout of their own, with no select to turn into anything
        if (true === ($view->vars['expanded'] ?? false)) {
            return;
        }

        // A remote-fed autocomplete keeps the widget whatever this view holds: its list is an endpoint, and the only options rendered are the value already selected - counting those would turn every one of them into a native select of a single line. Keyed on the attribute being *present* rather than on its value, EasyAdmin writing it as null when the target CRUD has no reachable route (see AssociationConfigurator): the field is a CrudAutocompleteType either way
        if (\array_key_exists('data-ea-autocomplete-endpoint-url', $attr)) {
            return;
        }

        // A widget of another name is a deliberate ask, and is left alone. "ea-autocomplete" is not one of those: EasyAdmin's ChoiceConfigurator writes exactly that on every non-expanded ChoiceField and AssociationField, as a form option, before this ever runs - so reading it as an ask is what kept the rule below from being applied on a single admin screen, and a three-value config select rendered as a search box
        $widget = $attr['data-ea-widget'] ?? null;
        if (null !== $widget && 'ea-autocomplete' !== $widget) {
            return;
        }

        // A multiple choice always gets it, however short: the widget renders removable tags, where a native multi-select asks for ctrl+click and shows one cramped scrolling box
        if (true !== ($view->vars['multiple'] ?? false) && $this->countChoices($view->vars['choices'] ?? []) < self::AUTOCOMPLETE_THRESHOLD) {
            // Removed, not merely left unset: below the threshold the rule is that there is no search box, and EasyAdmin has already put one there
            unset($view->vars['attr']['data-ea-widget']);

            return;
        }

        $view->vars['attr']['data-ea-widget'] = 'ea-autocomplete';
    }

    // Counts the options a list holds, a grouped one (<optgroup>) counting its own rather than itself
    private function countChoices(array $choices): int
    {
        $count = 0;
        foreach ($choices as $choice) {
            $count += $choice instanceof ChoiceGroupView ? $this->countChoices($choice->choices) : 1;
        }

        return $count;
    }
}
