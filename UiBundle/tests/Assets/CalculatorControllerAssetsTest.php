<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// A calculator is right with no JavaScript at all - the page is rendered with the results its defaults give. What the controller adds is the only thing the server cannot: following the inputs as they move
class CalculatorControllerAssetsTest extends TestCase
{
    private const string CONTROLLER_JS = 'assets/js/calculator.js';
    private const string BARREL = 'assets/controllers.js';
    private const string COMPONENT = 'templates/components/Form/Calculator.html.twig';
    private const string STYLESHEET = 'sass/_calculator.scss';

    // Kebab-case identifier: Stimulus derives the value attribute name from it, and a camelCase one would silently break "data-ui-calculator-url-value"
    public function testTheControllerIsRegisteredLazilyUnderTheNameTheTemplateWrites(): void
    {
        $this->assertStringContainsString("'ui-calculator': () => import('./js/calculator.js'),", $this->read(self::BARREL));
        $this->assertStringContainsString('data-controller="ui-calculator"', $this->read(self::COMPONENT));
        $this->assertStringContainsString('data-ui-calculator-url-value=', $this->read(self::COMPONENT));
        // The page's own language, so the numbers keep the format they were rendered in (see CalculatorController::compute())
        $this->assertStringContainsString("'_locale': app.request ? app.request.locale : null", $this->read(self::COMPONENT));
    }

    // The results are printed by the server before the controller ever connects, so a browser running no JS reads real numbers rather than empty slots
    public function testTheServerAlreadyPrintsEveryResultInTheMarkup(): void
    {
        $this->assertStringContainsString("results[output.name].formatted ?? '—'", $this->read(self::COMPONENT));
    }

    // Formatted server-side and printed as received - the currency, the decimals and the unit are the output's own settings, and re-deriving them in JS is where the two sides would drift
    public function testTheControllerPrintsWhatTheServerFormattedAndFormatsNothingItself(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('result?.formatted', $controller);
        $this->assertStringNotContainsString('Intl.NumberFormat', $controller);
        $this->assertStringNotContainsString('toFixed', $controller);
    }

    // A dragged slider fires an event per pixel: without the debounce and the abort, that is a request per pixel and answers landing out of order
    public function testASliderBeingDraggedIsDebouncedAndOnlyItsLastAnswerCounts(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static DEBOUNCE = 200;', $controller);
        $this->assertStringContainsString('clearTimeout(this.timer);', $controller);
        $this->assertStringContainsString('this.controller?.abort();', $controller);
    }

    // The whole arithmetic stays in PHP (see Service\ExpressionEvaluator): one formula, one implementation
    public function testNoExpressionIsEverEvaluatedInTheBrowser(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringNotContainsString('eval(', $controller);
        $this->assertStringNotContainsString('new Function', $controller);
    }

    // A calculator given an action also carries a name, an email and a message: only the controls a formula can read ride the GET, never those
    public function testOnlyTheControlsAFormulaReadsAreSentToTheServer(): void
    {
        $controller = $this->read(self::CONTROLLER_JS);

        $this->assertStringContainsString('static COMPUTED = \'input[type="number"][name], input[type="range"][name], input[type="checkbox"][name], select[name]\';', $controller);
        $this->assertStringContainsString('this.element.querySelectorAll(this.constructor.COMPUTED)', $controller);
        $this->assertStringContainsString('event.target.matches(this.constructor.COMPUTED)', $controller);
        $this->assertStringNotContainsString('querySelectorAll("input[name], select[name]")', $controller);
    }

    // A checkbox's value is "1" ticked or not: sending it as is would bill every switch, and leaving an unticked one out would bring its default back
    public function testASwitchIsSentByItsStateAndAlwaysSent(): void
    {
        $this->assertStringContainsString('"checkbox" === input.type ? (input.checked ? "1" : "0") : input.value', $this->read(self::CONTROLLER_JS));
    }

    // A yes/no field is drawn as the back office's switch, only inside a calculator
    public function testACalculatorCheckboxIsDrawnAsASwitch(): void
    {
        $stylesheet = $this->read(self::STYLESHEET);

        $this->assertMatchesRegularExpression('/\.ui-calculator input\[type="checkbox"\] \{[^}]*appearance: none;/', $stylesheet);
        $this->assertStringContainsString('.ui-calculator input[type="checkbox"]:checked::before', $stylesheet);
    }

    // A detail line worth nothing is rendered hidden and shown again by the controller once its option is chosen, rather than left out of the markup it would then have to be rebuilt into
    public function testADetailLineWorthNothingIsHiddenAndShownAgainWhenItCounts(): void
    {
        $component = $this->read(self::COMPONENT);
        $this->assertStringContainsString('{% if output.hiddenWhenZero %} data-ui-calculator-hide-zero{% if 0 == (results[output.name].value ?? 0) %} hidden{% endif %}{% endif %}', $component);

        $controller = $this->read(self::CONTROLLER_JS);
        $this->assertStringContainsString('cell.closest("[data-ui-calculator-hide-zero]")', $controller);
        $this->assertStringContainsString('row.hidden = !result?.value;', $controller);
        $this->assertMatchesRegularExpression('/\.ui-calculator-result\[hidden\] \{\s*display: none;/', $this->read(self::STYLESHEET));
    }

    // Given an action, the calculator is wrapped in a form element that must not become the grid's only item
    public function testASubmittableCalculatorKeepsItsTwoColumns(): void
    {
        $this->assertStringContainsString("'class': 'ui-calculator-form'", $this->read(self::COMPONENT));
        $this->assertMatchesRegularExpression('/\.ui-calculator-form \{\s*display: contents;/', $this->read(self::STYLESHEET));
    }

    // The "receive a copy" box sits in the fields column: a checkbox row matched as any descendant also caught the div wrapping every row, laying the whole calculator on one reversed line
    public function testTheCheckboxRowRuleOnlyMatchesTheCheckboxOwnRow(): void
    {
        $forms = $this->read('sass/_forms.scss');

        $this->assertStringContainsString('form div div:has(> input[type="checkbox"]) {', $forms);
        $this->assertStringNotContainsString('form div div:has(input[type="checkbox"])', $forms);
    }

    // No browser shows a range input's value, so a slider with no readout is a slider nobody can set
    public function testASliderIsGivenTheReadoutNoBrowserDraws(): void
    {
        $this->assertStringContainsString('document.createElement("output")', $this->read(self::CONTROLLER_JS));
        $this->assertStringContainsString('.ui-calculator-readout {', $this->read(self::STYLESHEET));
    }

    // A number changing under a slider would make the whole row jitter on proportional digits
    public function testTheResultsAreSetOnDigitsOfTheSameWidth(): void
    {
        $this->assertStringContainsString('font-variant-numeric: tabular-nums;', $this->read(self::STYLESHEET));
    }

    // The results are announced as they change, a visitor moving a slider with a screen reader otherwise hearing nothing at all
    public function testTheResultsZoneIsAnnouncedWhenItChanges(): void
    {
        $this->assertStringContainsString('aria-live="polite"', $this->read(self::COMPONENT));
    }

    // A form element only around a calculator given an action - with none, Enter would submit the page it sits on (rendered in CalculatorOutputsFirstTest)
    public function testTheCalculatorIsAFormElementOnlyWhenItHasAnAction(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringContainsString('{% set submittable = uiForm.action is not null %}', $component);
        $this->assertStringNotContainsString('<form', $component);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
