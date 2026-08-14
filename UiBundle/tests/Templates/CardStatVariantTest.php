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

// The variant declares itself by its own props, so a card given none of the five stays exactly the card it always was
class CardStatVariantTest extends TestCase
{
    public function testACardGivenNoneOfTheStatPropsKeepsThePlainMarkup(): void
    {
        $html = $this->render(['title' => 'Title', 'content' => 'Text']);

        $this->assertStringNotContainsString('card--stat', $html);
        $this->assertStringNotContainsString('card-data', $html);
        $this->assertStringContainsString('Text', $html);
    }

    // Each of the five opens the variant on its own, none of them being more the subject of the card than the others
    public function testAnyOneOfTheStatPropsOpensTheVariant(): void
    {
        foreach ([['src' => 'cover.jpg'], ['eyebrow' => 'Chaos'], ['rating' => 4], ['stats' => [['label' => 'Int.', 'value' => 900]]]] as $props) {
            $this->assertStringContainsString('card--stat', $this->render($props + ['title' => 'Title']), sprintf('"%s" should open the stat variant.', array_key_first($props)));
        }
    }

    // A rating of 0 is a real score - "not rated" is the field left empty, and a card scored zero still opens the variant
    public function testAZeroRatingIsAScoreAndNotAnEmptyField(): void
    {
        $this->assertStringContainsString('card--stat', $this->render(['title' => 'Title', 'rating' => 0]));
        $this->assertStringContainsString('Progress:Rating value="0"', $this->render(['title' => 'Title', 'rating' => 0]));
        $this->assertStringNotContainsString('card--stat', $this->render(['title' => 'Title', 'rating' => '']));
    }

    public function testTheKeyFiguresArePairedLabelWithValue(): void
    {
        $html = $this->render(['title' => 'Title', 'stats' => [['label' => 'Int.', 'value' => 900], ['label' => 'Chance', 'value' => 40]]]);

        $this->assertSame(2, substr_count($html, 'class="card-stats-item'));
        $this->assertStringContainsString('<span class="card-stats-label">Int.</span>', $html);
        $this->assertStringContainsString('<strong class="card-stats-value">900</strong>', $html);
    }

    // The column is written on the cell by the component, CSS having no way to tell which one a cell falls in
    public function testTheFiguresOfOneLineAreMarkedSoTheyMeetInTheMiddle(): void
    {
        $html = $this->render(['title' => 'Title', 'stats' => [['label' => 'Int.', 'value' => 900], ['label' => 'Chance', 'value' => 40], ['label' => 'Force', 'value' => 60]]]);

        $this->assertSame(2, substr_count($html, 'card-stats-item--end'));
    }

    // A full-width figure opens a fresh line, so the counter restarts behind it and the next one falls back on the left
    public function testAWideFigureTakesTheWholeLineAndRestartsTheColumns(): void
    {
        $html = $this->render(['title' => 'Title', 'stats' => [['label' => 'Int.', 'value' => 900], ['label' => 'Signe', 'value' => 'Chaos', 'wide' => true], ['label' => 'Force', 'value' => 60]]]);

        $this->assertSame(1, substr_count($html, 'card-stats-item--wide'));
        $this->assertSame(2, substr_count($html, 'card-stats-item--end'));
    }

    // The picture is linked to the card's own destination, so it is not a dead zone in the middle of a clickable card
    public function testThePictureFollowsTheCardsOwnLink(): void
    {
        $html = $this->render(['title' => 'Title', 'src' => 'cover.jpg', 'titleUrl' => '/page']);

        $this->assertStringContainsString('src="cover.jpg"', $html);
        $this->assertStringContainsString('url="/page"', $html);
    }

    // A card reduced to its title is a legitimate call, and a self-closing one nests nothing at all
    public function testACardNestingNothingRendersWithoutAnError(): void
    {
        $html = $this->render(['title' => 'Title']);

        $this->assertStringContainsString('<div class="card-body">', $html);
    }

    /**
     * The bare environment the component renderer would otherwise bring: the "props" tag and the "attributes" it empties, without which the template no longer parses.
     *
     * @param array<string, mixed>       $context
     * @param array<string, string|bool> $attributes what a caller wrote on top of the declared props
     */
    private function render(array $context, array $attributes = []): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));
        $twig->addTokenParser(new PropsTokenParser());
        // What TwigEnvironmentConfigurator does in the application: without it the rendered attributes come back escaped, quotes included
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);

        return $twig->render('components/Card/Card.html.twig', $context + [
            'attributes' => new ComponentAttributes($attributes, $twig->getRuntime(EscaperRuntime::class)),
        ]);
    }
}
