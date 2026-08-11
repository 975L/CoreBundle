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

// The form component renders the flash bag itself, for the standalone re-render a failed submission goes through - no site layout has consumed it there. Both rules below were once broken at the same spot
class FormFlashMarkupTest extends TestCase
{
    // FormController flashes "warning" when the rate limiter trips (see submit()), and the visitor is redirected: a type the component does not read makes that message silently disappear
    public function testTheComponentRendersEveryFlashTypeTheControllerPuts(): void
    {
        $html = $this->render([
            'success' => ['Envoi reçu'],
            'danger' => ['Envoi refusé'],
            'warning' => ['Trop de tentatives'],
        ]);

        $this->assertStringContainsString('<div class="alert alert-success">Envoi reçu</div>', $html);
        $this->assertStringContainsString('<div class="alert alert-danger">Envoi refusé</div>', $html);
        $this->assertStringContainsString('<div class="alert alert-warning">Trop de tentatives</div>', $html);
    }

    // The bag is shared with every other bundle and app, and this component is rendered on the public front: a flash interpolating request data must not reach the page as markup
    public function testAFlashIsEscaped(): void
    {
        $html = $this->render(['danger' => ['<script>alert(1)</script>']]);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /** @param array<string, string[]> $flashes */
    private function render(array $flashes): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        // Untranslated keys come back as-is, which is enough for the flash markup read above
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // The form and routing layers play no part in the flash markup, so they are stubbed away
        $twig->addFunction(new TwigFunction('form_start', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('form_widget', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('form_end', static fn (): string => '', ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters = []): string => '/'));

        return $twig->render('components/Form/Form.html.twig', [
            'app' => new readonly class ($flashes) {
                /** @param array<string, string[]> $flashes */
                public function __construct(private array $flashes)
                {
                }

                /**
                 * @param string[] $types
                 *
                 * @return array<string, string[]>
                 */
                public function flashes(array $types): array
                {
                    return array_intersect_key($this->flashes, array_flip($types));
                }
            },
            'form' => [],
            'uiForm' => ['name' => 'contact', 'links' => []],
        ]);
    }
}
