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

// An icon is four values or nothing at all, and "nothing at all" reaches the card in more than one shape
class CardIconTest extends TestCase
{
    // Section:Features passes [] for a card given no icon, and [] != "" is true in Twig - the card used to read icon[0] on an empty array and take the whole page down with it
    public function testAnEmptyArrayIconIsNoIconRatherThanAnError(): void
    {
        $html = $this->render(['title' => 'Title', 'icon' => []]);

        $this->assertStringNotContainsString('Image:Icon', $html);
        $this->assertStringContainsString('<h2 class="card-header">Title</h2>', $html);
    }

    // The prop's own default, left untouched by a caller who has no icon to give
    public function testAnEmptyStringIconIsNoIconEither(): void
    {
        $html = $this->render(['title' => 'Title', 'icon' => '']);

        $this->assertStringNotContainsString('Image:Icon', $html);
    }

    public function testTheFourValuesOfAnIconReachTheHeader(): void
    {
        $html = $this->render(['title' => 'Title', 'icon' => ['star.svg', 'icon-accent', 22, 22]]);

        $this->assertStringContainsString('src="star.svg"', $html);
        $this->assertStringContainsString('class="icon-accent"', $html);
        $this->assertStringContainsString('width="22"', $html);
        $this->assertStringContainsString('height="22"', $html);
    }

    /**
     * The bare environment the component renderer would otherwise bring: the "props" tag and the "attributes" it empties, without which the template no longer parses.
     *
     * @param array<string, mixed> $context
     */
    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PropsTokenParser());
        // What TwigEnvironmentConfigurator does in the application: without it the rendered attributes come back escaped, quotes included
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render('components/Card/Card.html.twig', $context + [
            'attributes' => new ComponentAttributes([], $twig->getRuntime(EscaperRuntime::class)),
        ]);
    }
}
