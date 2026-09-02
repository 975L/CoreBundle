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
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

// What a backend that does not answer shows the reader. It used to be the response's own error key - the word "unavailable", printed as if it were the answer - where the message and its link are written here, in the reader's language, and only revealed by ai-assistant.js
class AiAssistantErrorMessageTest extends TestCase
{
    // Hidden until something fails, and never built from the response: the link the reader follows is the one the caller passed
    public function testTheWidgetCarriesAHiddenErrorMessage(): void
    {
        $html = $this->render();

        $this->assertStringContainsString('data-ai-assistant-target="error"', $html);
        $this->assertStringContainsString('d-none', $html);
        $this->assertStringContainsString('label.ai_assistant_error', $html);
    }

    // Where a reader goes from here is the caller's to say, so no link at all is a legitimate state
    public function testTheHelpLinkIsWrittenOnlyWhenTheCallerPassesOne(): void
    {
        $this->assertStringNotContainsString('<a href', $this->render());

        $withLink = $this->render(['help_url' => '/management/config/12', 'help_label' => 'Vérifier son endpoint']);
        $this->assertStringContainsString('<a href="/management/config/12">', $withLink);
        $this->assertStringContainsString('Vérifier son endpoint', $withLink);
    }

    // The other half of the same behaviour: an error key reaching the log again would read exactly like an answer
    public function testTheScriptNeverPrintsTheResponseErrorKey(): void
    {
        $script = (string) file_get_contents(\dirname(__DIR__, 2) . '/assets/js/ai-assistant.js');

        $this->assertStringNotContainsString('data.error', $script);
        $this->assertStringContainsString('showError()', $script);
    }

    // "path" and "csrf_token" come from the app; "ai_assistant_name" from AiRephraseExtension, covered on its own
    private function render(array $context = []): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $id): string => 'token'));
        $twig->addFunction(new TwigFunction('ai_assistant_name', static fn (): string => 'Donovan'));

        return $twig->render('management/_ai_assistant_widget.html.twig', $context);
    }
}
