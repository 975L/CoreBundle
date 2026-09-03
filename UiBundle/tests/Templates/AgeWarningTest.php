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
use Symfony\UX\TwigComponent\ComponentAttributes;
use Symfony\UX\TwigComponent\Twig\PropsTokenParser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFunction;

// The guard lives in the component rather than in the templates calling it, so a site with no age warning to state renders nothing from a call it never had to wrap
class AgeWarningTest extends TestCase
{
    public function testTheSentenceTheSiteWroteReachesAnAlert(): void
    {
        $html = $this->render('Public majeur et averti.');

        $this->assertStringContainsString('Alert:Alert', $html);
        $this->assertStringContainsString('Public majeur et averti.', $html);
    }

    // What every site but the few stating an age restriction gets: the config ships empty, and a book or a product carrying an age still renders nothing here
    public function testNothingIsRenderedWhenTheSiteWroteNone(): void
    {
        $this->assertSame('', trim($this->render(null)));
        $this->assertSame('', trim($this->render('')));
    }

    // The bare environment the component renderer would otherwise bring: the "props" tag, the "attributes" it empties, and the config() function this fragment reads its sentence from. The nested <twig:...> call is left as text, which is exactly what the first test reads it as
    private function render(?string $ageWarning): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PropsTokenParser());
        $twig->addFunction(new TwigFunction('config', static fn (string $slug): ?string => 'site-age-warning' === $slug ? $ageWarning : null));
        // What TwigEnvironmentConfigurator does in the application: without it the rendered attributes come back escaped, quotes included
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render('components/Alert/AgeWarning.html.twig', [
            'attributes' => new ComponentAttributes([], $twig->getRuntime(EscaperRuntime::class)),
        ]);
    }
}
