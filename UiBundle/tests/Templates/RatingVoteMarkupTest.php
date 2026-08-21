<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Twig\BoolExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The widget a visitor clicks. What it must never print is the visitor's own vote: the page carrying it is public and shared (a book's is cached for an hour), so the row it opens on is everyone's average and nothing else
class RatingVoteMarkupTest extends TestCase
{
    public function testItOffersOneButtonPerIconOfTheScale(): void
    {
        $html = $this->render(['scale' => 5]);

        $this->assertSame(5, substr_count($html, '<button type="button"'));
        $this->assertSame(5, substr_count($html, 'data-action="ui-rating#vote"'));
        $this->assertStringContainsString('data-ui-rating-value-param="5"', $html);
    }

    // The row opens on the rounded average, which is what everyone gets from the cache; the visitor's own vote is painted over it by assets/js/rating.js
    public function testTheRowOpensOnTheRoundedAverage(): void
    {
        $html = $this->render(['scale' => 5, 'average' => 4.2, 'count' => 37]);

        $this->assertSame(4, substr_count($html, 'rating-star rating-star--on'));
        $this->assertStringNotContainsString('rating-vote--voted', $html);
    }

    public function testNothingIsLitOnAThingNobodyVotedOn(): void
    {
        $html = $this->render(['scale' => 5, 'average' => 0.0, 'count' => 0]);

        $this->assertStringNotContainsString('rating-star--on', $html);
        $this->assertStringContainsString('label.rating_none', $html);
    }

    // On a real scale the average is what the tally leads with
    public function testTheTallyPrintsTheAverageOutOfTheScale(): void
    {
        $html = $this->render(['scale' => 5, 'average' => 4.2, 'count' => 37]);

        $this->assertStringContainsString('<span class="rating-average">4.2/5</span>', $html);
        $this->assertStringContainsString('label.rating_votes_many', $html);
    }

    // Un trait entre les deux : « 4/5 » suivi de « 1 avis » se lisait « 4/51 avis », les deux nombres se touchant
    public function testTheAverageAndTheCountAreToldApart(): void
    {
        $html = $this->render(['scale' => 5, 'average' => 4.0, 'count' => 1]);

        $this->assertStringContainsString('</span> - label.rating_votes_one', $html);
    }

    // A single icon is a "like": the average of a column of ones says nothing, so only the count is printed
    public function testASingleIconPrintsNoAverage(): void
    {
        $html = $this->render(['scale' => 1, 'average' => 1.0, 'count' => 37]);

        // The class, not the bare word: "data-ui-rating-average-value" carries the average for the controller either way
        $this->assertStringNotContainsString('class="rating-average"', $html);
        $this->assertStringContainsString('label.rating_votes_many', $html);
    }

    public function testASingleVoteReadsAsOne(): void
    {
        $this->assertStringContainsString('label.rating_votes_one', $this->render(['scale' => 5, 'average' => 4.0, 'count' => 1]));
    }

    // One icon means "like" and its button says so, where a scale names the score it stands for
    public function testTheButtonsAreNamedAfterWhatTheyDo(): void
    {
        $this->assertStringContainsString('label.rating_like', $this->render(['scale' => 1]));
        $this->assertStringContainsString('label.rating_give', $this->render(['scale' => 5]));
    }

    // The site's chosen glyph is set on the row, so one class swaps the whole scale (see sass/_rating.scss)
    public function testTheChosenIconIsCarriedByTheRow(): void
    {
        $this->assertStringContainsString('class="rating rating--heart"', $this->render(['icon' => 'heart']));
    }

    // Everything assets/js/rating.js reads has to be written here, values and targets alike
    public function testItCarriesWhatItsControllerReads(): void
    {
        $html = $this->render([]);

        foreach (['url', 'key', 'scale', 'count', 'average', 'none-label', 'one-label', 'many-label', 'error-label'] as $value) {
            $this->assertStringContainsString('data-ui-rating-' . $value . '-value="', $html);
        }
        foreach (['row', 'star', 'tally'] as $target) {
            $this->assertStringContainsString('data-ui-rating-target="' . $target . '"', $html);
        }
        $this->assertStringContainsString('data-controller="ui-rating"', $html);
    }

    public function testTheKeyNamesTheThingBeingRated(): void
    {
        $this->assertStringContainsString('data-ui-rating-key-value="book:42"', $this->render([]));
    }

    // A catalog card shows the score and the way to set it, not how many voted: the visitor is choosing between items there, not reading up on one
    public function testACompactWidgetPrintsTheScoreWithoutTheCount(): void
    {
        $html = $this->render(['scale' => 5, 'average' => 4.2, 'count' => 37], true);

        // The tally itself and not the whole widget: the labels the controller writes are carried as data attributes either way
        $this->assertStringContainsString('<span class="rating-average">4.2/5</span></p>', $html);
        $this->assertStringContainsString('rating-vote--compact', $html);
    }

    // The empty row of icons already says nobody voted, where the sentence would take the width of the card
    public function testACompactWidgetSaysNothingOnAThingNobodyVotedOn(): void
    {
        $html = $this->render(['scale' => 5, 'average' => 0.0, 'count' => 0], true);

        // Emptied, not dropped: assets/js/rating.js writes the new tally, and an error, into this very element
        $this->assertStringContainsString('data-ui-rating-target="tally" aria-live="polite"></p>', $html);
    }

    // A single icon has no average to drop the count for, so the count stays even compact
    public function testACompactSingleIconKeepsTheCount(): void
    {
        $this->assertStringContainsString('aria-live="polite">label.rating_votes_many</p>', $this->render(['scale' => 1, 'average' => 1.0, 'count' => 37], true));
    }

    // What the controller reads to tell the two shapes apart
    public function testTheShapeIsCarriedForTheController(): void
    {
        $this->assertStringContainsString('data-ui-rating-compact-value="false"', $this->render([]));
        $this->assertStringContainsString('data-ui-rating-compact-value="true"', $this->render([], true));
    }

    // The symmetrical form of the documented compact="true": a Twig component attribute written literally arrives as the string "false", which is truthy on its own and would render the very shape it asks against
    public function testAWidgetToldNotToBeCompactIsNot(): void
    {
        $html = $this->render([], 'false');

        $this->assertStringNotContainsString('rating-vote--compact', $html);
        $this->assertStringContainsString('data-ui-rating-compact-value="false"', $html);
    }

    private function render(array $rating, mixed $compact = false): string
    {
        $rating += ['ownerType' => 'book', 'ownerId' => 42, 'scale' => 5, 'icon' => 'star', 'average' => 0.0, 'count' => 0];

        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        // Untranslated keys come back as-is, which is what the assertions above read
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        // The bundle's own filter and not a cast: a cast reads the string "false" as true, which is the very thing the template applies it against
        $twig->addFilter(new TwigFilter('to_bool', new BoolExtension()->toBool(...)));
        // The aggregate query is RatingRuntime's business (see RatingRuntimeTest); what is checked here is the markup built from what it answers
        $twig->addFunction(new TwigFunction('ui_rating', static fn (): array => $rating));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters = []): string => '/rating/' . $parameters['ownerType'] . '/' . $parameters['ownerId']));

        return $twig->render('components/Rating/Rating.html.twig', ['ownerType' => 'book', 'ownerId' => 42, 'compact' => $compact]);
    }
}
