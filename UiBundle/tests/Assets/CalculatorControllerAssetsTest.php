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

    // Nothing is posted anywhere: a <form> around it would let Enter submit the page it sits on
    public function testTheCalculatorIsNotAFormElement(): void
    {
        $component = $this->read(self::COMPONENT);

        $this->assertStringNotContainsString('form_start(', $component);
        $this->assertStringNotContainsString('<form', $component);
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
