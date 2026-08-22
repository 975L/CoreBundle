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

// The layout renders this component on every page of the site, so what it does when there is nothing to show matters as much as what it draws when there is
class DebugPreviewMarkupTest extends TestCase
{
    // One frame per email held back, in the order they were stashed - a submission sending a message and its copy shows both
    public function testOneFrameIsDrawnPerPreview(): void
    {
        $html = $this->render(['<html>to</html>', '<html>copy</html>']);

        $this->assertSame(2, substr_count($html, '<iframe class="email-debug-preview"'));
        $this->assertStringContainsString('&lt;html&gt;to&lt;', $html);
        $this->assertStringContainsString('&lt;html&gt;copy&lt;', $html);
    }

    // A whole html document goes into an attribute: unescaped, the first quote of the email would close srcdoc and pour the rest into the page
    public function testThePreviewIsEscapedIntoTheAttribute(): void
    {
        $html = $this->render(['<p title="x">body</p>']);

        $this->assertStringNotContainsString('<p title=', $html);
        $this->assertStringContainsString('sandbox', $html);
    }

    // The ordinary page, debug mode off: no frame, and nothing left over between the flashes and the content above them
    public function testNothingIsDrawnWithoutAPreview(): void
    {
        $this->assertStringNotContainsString('<iframe', $this->render([]));
    }

    // Reading the stash starts the session, so an anonymous visitor must not reach it at all - the same guard the flashes block carries, see FlashExtensionTest
    public function testTheStashIsNotEvenReadForAVisitorWhoCannotHoldAFlash(): void
    {
        $read = false;
        $html = $this->render(['<html>to</html>'], false, $read);

        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertFalse($read);
    }

    /** @param string[] $previews */
    private function render(array $previews, bool $canHoldFlash = true, ?bool &$read = null): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        // Untranslated keys come back as-is, which is enough for the markup read above
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // What EmailDebugExtension answers from the real session - covered on its own in EmailDebugExtensionTest
        $twig->addFunction(new TwigFunction('ui_email_debug_previews', static function () use ($previews, &$read): array {
            $read = true;

            return $previews;
        }));
        // What FlashExtension answers from the real request - covered on its own in FlashExtensionTest
        $twig->addFunction(new TwigFunction('ui_can_hold_flash', static fn (): bool => $canHoldFlash));

        return $twig->render('components/Email/DebugPreview.html.twig');
    }
}
