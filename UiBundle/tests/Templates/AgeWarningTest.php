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

// Both guards live in the component rather than in the templates calling it, so an item declaring no age, and a site leaving the setting empty, render nothing from a call neither had to wrap
class AgeWarningTest extends TestCase
{
    /** @var list<array{0: string, 1: ?string}> */
    private array $asked = [];

    public function testTheSentenceTheSiteWroteReachesAnAlert(): void
    {
        $html = $this->render('3-6', null, 'Public majeur et averti.');

        $this->assertStringContainsString('Alert:Alert', $html);
        $this->assertStringContainsString('Public majeur et averti.', $html);
    }

    // The item's own language, which is what the request's could not be: a book written in English is not read the sentence typed in French on a site that translated the setting
    public function testTheItemsLanguageIsTheOneTheSettingIsAskedIn(): void
    {
        $this->render('7-10', 'en', 'Recommended for young readers.');

        $this->assertSame([['site-age-warning', 'en']], $this->asked);
    }

    // A catalog of children's books installs the very same bundle: an item declaring no age asks for no sentence at all
    public function testNothingIsRenderedWithoutAnAge(): void
    {
        $this->assertSame('', trim($this->render(null, null, 'Public majeur et averti.')));
        $this->assertSame('', trim($this->render('', null, 'Public majeur et averti.')));
        $this->assertSame([], $this->asked);
    }

    // The call this component shipped with, and the one BookBundle and ShopBundle still make from their own release: <twig:c975LUi:Alert:AgeWarning/>, no attribute at all, must keep reading the sentence the site wrote - a site upgrading this bundle first would otherwise lose the mention with nothing saying so
    public function testTheCallWithNoAgeAtAllStillReadsTheSentence(): void
    {
        $html = $this->render(null, null, 'Public majeur et averti.', omitAge: true);

        $this->assertStringContainsString('Public majeur et averti.', $html);
        $this->assertSame([['site-age-warning', null]], $this->asked);
    }

    // What every site but the few stating an age restriction gets: the setting ships empty, and an item carrying an age still renders nothing
    public function testNothingIsRenderedWhenTheSiteWroteNone(): void
    {
        $this->assertSame('', trim($this->render('3-6', null, null)));
        $this->assertSame('', trim($this->render('3-6', null, '')));
    }

    // The bare environment the component renderer would otherwise bring: the "props" tag, the "attributes" it empties, and the config() function this fragment reads its sentence from. The nested <twig:...> call is left as text, which is exactly what the first test reads it as
    private function render(?string $age, ?string $locale, ?string $sentence, bool $omitAge = false): string
    {
        $this->asked = [];
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PropsTokenParser());
        $twig->addFunction(new TwigFunction('config', function (string $slug, ?string $locale = null) use ($sentence): ?string {
            $this->asked[] = [$slug, $locale];

            return 'site-age-warning' === $slug ? $sentence : null;
        }));
        // What TwigEnvironmentConfigurator does in the application: without it the rendered attributes come back escaped, quotes included
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        $context = [
            'locale' => $locale,
            'attributes' => new ComponentAttributes([], $twig->getRuntime(EscaperRuntime::class)),
        ];

        // Not passed and passed empty are two different calls, and the component tells them apart: an attribute the caller never wrote is absent from the context, where an empty one is a key holding '' (see ComponentRenderer, which spreads the input props into the variables)
        if (!$omitAge) {
            $context['age'] = $age;
        }

        return $twig->render('components/Alert/AgeWarning.html.twig', $context);
    }
}
