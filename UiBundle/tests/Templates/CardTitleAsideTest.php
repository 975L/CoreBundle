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

// A mention at the end of the band splits the header in two spans, and a card without one keeps the markup the sites styling ".card-header > a" are written against
class CardTitleAsideTest extends TestCase
{
    // The guarantee the two spans are conditional for: nothing wraps a title standing alone
    public function testACardWithoutAMentionKeepsItsPlainHeader(): void
    {
        $html = $this->render(['title' => 'Title']);

        $this->assertStringContainsString('<h2 class="card-header">Title</h2>', $html);
        $this->assertStringNotContainsString('card-header__', $html);
    }

    // The link stays a direct child of the header as long as there is nothing beside it
    public function testALinkedTitleWithoutAMentionStaysADirectChildOfTheHeader(): void
    {
        $html = $this->render(['title' => 'Title', 'titleUrl' => '/book/title']);

        $this->assertStringContainsString('<h2 class="card-header"><a href="/book/title">Title</a></h2>', $html);
    }

    public function testAMentionIsRenderedAtTheEndOfTheBand(): void
    {
        $html = $this->render(['title' => 'Title', 'titleAside' => '15/09/2026']);

        $this->assertStringContainsString('<span class="card-header__title">Title</span>', $html);
        $this->assertStringContainsString('<span class="card-header__aside">15/09/2026</span>', $html);
    }

    // The title span holds the link, the mention sitting outside it - what makes only one of the two clickable
    public function testALinkedTitleIsWrappedWithItsOwnSpan(): void
    {
        $html = $this->render(['title' => 'Title', 'titleUrl' => '/book/title', 'titleAside' => '9,90 €']);

        $this->assertStringContainsString('<span class="card-header__title"><a href="/book/title">Title</a></span>', $html);
        $this->assertStringContainsString('<span class="card-header__aside">9,90 €</span>', $html);
    }

    // The header has two branches and the icon one is the other: the icon belongs to the title, not to the band
    public function testAnIconIsHeldByTheTitleSpanRatherThanByTheBand(): void
    {
        $html = $this->render(['title' => 'Title', 'titleAside' => '3 avis', 'icon' => ['star.svg', 'icon-accent', 22, 22]]);

        $this->assertStringContainsString('<span class="card-header__title"><twig:c975LUi:Image:Icon src="star.svg"', $html);
        $this->assertStringContainsString('<span class="card-header__aside">3 avis</span>', $html);
    }

    // An empty mention is the prop's own default, left untouched by a caller who has none to give
    public function testAnEmptyMentionIsNoMention(): void
    {
        $html = $this->render(['title' => 'Title', 'titleAside' => '']);

        $this->assertStringNotContainsString('card-header__', $html);
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
