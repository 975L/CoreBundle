<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Entity\Form;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// Which column a calculator is read first is the admin's call, and the template carries it as a class rather than as a second markup: sass/_calculator.scss reorders the same DOM, so the panel keeps its place in the reading order whatever the switch says
class CalculatorOutputsFirstTest extends TestCase
{
    // The switch on, and the stylesheet has the hook it reorders on
    public function testTheClassIsCarriedWhenTheAdminAsksForTheResultsFirst(): void
    {
        $html = $this->render(true);

        $this->assertStringContainsString('class="ui-calculator ui-calculator-outputs-first"', $html);
    }

    // The switch off, which is what every existing calculator keeps: the fields are read first, as they always were
    public function testNothingIsAddedWhileTheSwitchIsOff(): void
    {
        $html = $this->render(false);

        $this->assertStringContainsString('class="ui-calculator"', $html);
        $this->assertStringNotContainsString('ui-calculator-outputs-first', $html);
    }

    // Renders the calculator component against a Form carrying nothing but the switch under test
    private function render(bool $outputsFirst): string
    {
        $form = new Form()
            ->setName('economies-e85')
            ->setOutputsFirst($outputsFirst)
        ;

        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['text.calculator_estimate' => 'An estimate.'], 'en', 'ui');
        $twig->addExtension(new TranslationExtension($translator));
        // The form and routing layers play no part in the class this test varies
        $twig->addFunction(new TwigFunction('form_widget', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters = []): string => '/'));

        return $twig->render('components/Form/Calculator.html.twig', [
            'uiForm' => $form,
            'form' => null,
            'results' => [],
        ]);
    }
}
