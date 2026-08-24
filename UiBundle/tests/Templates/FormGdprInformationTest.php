<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// What every public form now says instead of asking the visitor to tick a consent box - the line is the whole of what article 13 is answered with, so a site showing none is a site saying nothing
class FormGdprInformationTest extends TestCase
{
    // The setting names the page the line points at, and the line is drawn under the form once it is filled
    public function testTheInformationLineLinksToThePageTheSettingNames(): void
    {
        $html = $this->render('https://example.com/pages/privacy-policy');

        $this->assertStringContainsString('<p class="text-muted small">', $html);
        $this->assertStringContainsString('<a href="https://example.com/pages/privacy-policy">Privacy policy</a>', $html);
    }

    // A line pointing at nothing is worse than none: an admin who has not filled the setting in gets no dangling link and no empty paragraph
    public function testNothingIsDrawnWhileTheSettingIsEmpty(): void
    {
        foreach ([null, ''] as $value) {
            $html = $this->render($value);

            $this->assertStringNotContainsString('text-muted small', $html);
            $this->assertStringNotContainsString('Privacy policy', $html);
        }
    }

    // Renders the form component against the "ui" catalogue's own wording, the config function answering what the admin typed
    private function render(?string $privacyUrl): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        // The very shape the three "ui" catalogues carry: the anchor lives in the translation, the setting only fills its href
        $translator->addResource('array', ['text.gdpr_information' => 'The data you enter here is only used to handle your request. Your rights are detailed in the <a href="%privacyUrl%">Privacy policy</a>.'], 'en', 'ui');
        $twig->addExtension(new TranslationExtension($translator));
        // The form, routing and flash layers play no part in the information line, so they are stubbed away
        $twig->addFunction(new TwigFunction('form_start', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('form_widget', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('form_end', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters = []): string => '/'));
        $twig->addFunction(new TwigFunction('ui_can_hold_flash', static fn (): bool => false));
        // ConfigBundle's own function, the one thing this test varies
        $twig->addFunction(new TwigFunction('config', static fn (string $slug): mixed => 'url-privacy-policy' === $slug ? $privacyUrl : null));

        return $twig->render('components/Form/Form.html.twig', [
            'app' => new readonly class {
                /** @return array<string, string[]> */
                public function flashes(array $types): array
                {
                    return [];
                }
            },
            'form' => [],
            'uiForm' => ['name' => 'contact', 'links' => []],
        ]);
    }
}
