<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form\Extension;

use c975L\UiBundle\Form\AnimationChoiceType;
use c975L\UiBundle\Form\Extension\ChoiceAutocompleteExtension;
use c975L\UiBundle\Form\ImageClassChoiceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;

// One rule for every choice field of every bundle: the size of the list decides the widget, not which bundle wrote the field. Driven over real forms rather than over the extension alone, the count being read off the built view - which is the only place a lazily loaded list (EntityType) knows how long it is.
class ChoiceAutocompleteExtensionTest extends TestCase
{
    public function testAShortListStaysANativeSelect(): void
    {
        $view = $this->view(['choices' => array_flip(range(1, ChoiceAutocompleteExtension::AUTOCOMPLETE_THRESHOLD - 1))]);

        $this->assertArrayNotHasKey('data-ea-widget', $view->vars['attr'], 'A list short enough to read at a glance was turned into a search box.');
    }

    public function testALongListBecomesSearchable(): void
    {
        $view = $this->view(['choices' => array_flip(range(1, ChoiceAutocompleteExtension::AUTOCOMPLETE_THRESHOLD))]);

        $this->assertSame('ea-autocomplete', $view->vars['attr']['data-ea-widget'] ?? null);
    }

    // Grouped options count as their own options, not as one per <optgroup> - the block kind picker is the one list of the admin that carries categories
    public function testGroupedOptionsAreCountedOneByOne(): void
    {
        $choices = [];
        foreach (range(1, ChoiceAutocompleteExtension::AUTOCOMPLETE_THRESHOLD) as $index) {
            $choices['group ' . ($index % 3)]['label ' . $index] = $index;
        }

        $view = $this->view(['choices' => $choices]);

        $this->assertSame('ea-autocomplete', $view->vars['attr']['data-ea-widget'] ?? null);
    }

    // A native multi-select asks for ctrl+click, whatever its length
    public function testAMultipleChoiceIsAlwaysSearchable(): void
    {
        $view = $this->view(['choices' => ['a' => 'a', 'b' => 'b'], 'multiple' => true]);

        $this->assertSame('ea-autocomplete', $view->vars['attr']['data-ea-widget'] ?? null);
    }

    // Radios and checkboxes hold no select to turn into anything
    public function testExpandedChoicesAreLeftAlone(): void
    {
        $view = $this->view(['choices' => array_flip(range(1, 20)), 'expanded' => true]);

        $this->assertArrayNotHasKey('data-ea-widget', $view->vars['attr'], 'A list of checkboxes was handed the select widget.');
    }

    // A widget of another name is a deliberate ask
    public function testAnExplicitWidgetIsKept(): void
    {
        $view = $this->view(['choices' => array_flip(range(1, 20)), 'attr' => ['data-ea-widget' => 'ea-autocomplete-custom']]);

        $this->assertSame('ea-autocomplete-custom', $view->vars['attr']['data-ea-widget']);
    }

    // The blind spot this rule was written against, and the one it had: EasyAdmin's ChoiceConfigurator writes exactly "ea-autocomplete" on every non-expanded ChoiceField as a form option, before this extension runs. Read as a field asking for the widget, it exempted every select of every admin screen - which is how a three-value config entry went on rendering as a search box
    public function testEasyAdminsOwnDefaultIsOverriddenOnAShortList(): void
    {
        $view = $this->view(['choices' => ['a' => 'a', 'b' => 'b', 'c' => 'c'], 'attr' => ['data-ea-widget' => 'ea-autocomplete']]);

        $this->assertArrayNotHasKey('data-ea-widget', $view->vars['attr'], 'EasyAdmin\'s blanket widget survived on a list of three, so the size rule decides nothing on an admin screen.');
    }

    // Overridden, not merely ignored: a long list EasyAdmin already marked keeps the widget rather than being handed a second one
    public function testEasyAdminsOwnDefaultIsKeptOnALongList(): void
    {
        $view = $this->view(['choices' => array_flip(range(1, 20)), 'attr' => ['data-ea-widget' => 'ea-autocomplete']]);

        $this->assertSame('ea-autocomplete', $view->vars['attr']['data-ea-widget']);
    }

    // A list fed by an endpoint renders only the value already selected, so counting its options would make a native select of one line out of every remote autocomplete. Keyed on the attribute's presence: EasyAdmin writes it as null when the target CRUD has no reachable route, and the field is a remote one either way
    public function testARemoteFedAutocompleteIsLeftAlone(): void
    {
        foreach (['/admin?routeName=autocomplete', null] as $endpoint) {
            $view = $this->view(['choices' => ['a' => 'a'], 'attr' => [
                'data-ea-widget' => 'ea-autocomplete',
                'data-ea-autocomplete-endpoint-url' => $endpoint,
            ]]);

            $this->assertSame('ea-autocomplete', $view->vars['attr']['data-ea-widget'] ?? null, 'A remote-fed autocomplete was turned into a native select holding its current value alone.');
        }
    }

    // The entrance animation is the field the rule was written for: eight choices plus a "none" placeholder that TomSelect drops from its own list, leaving an editor unable to remove an animation once picked
    public function testTheAnimationFieldKeepsItsSelectableNoneOption(): void
    {
        $view = $this->factory()->create(AnimationChoiceType::class)->createView();

        $this->assertArrayNotHasKey('data-ea-widget', $view->vars['attr']);
        $this->assertNotNull($view->vars['placeholder']);
    }

    // The css classes picker, on the other hand, is a multiple choice of fifteen: it keeps the removable-tags widget it used to ask for by hand
    public function testTheCssClassesFieldStaysSearchable(): void
    {
        $view = $this->factory()->create(ImageClassChoiceType::class)->createView();

        $this->assertSame('ea-autocomplete', $view->vars['attr']['data-ea-widget'] ?? null);
    }

    private function view(array $options): FormView
    {
        return $this->factory()->create(ChoiceType::class, null, $options)->createView();
    }

    private function factory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([], [ChoiceType::class => [new ChoiceAutocompleteExtension()]]))
            ->getFormFactory();
    }
}
