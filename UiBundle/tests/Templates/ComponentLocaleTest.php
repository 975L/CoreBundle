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
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// The two components a page reading in one language whatever language the visitor arrived in hands a "locale": their own words then follow the text, not the visitor. Left out, the visitor's own is what they speak, which is a null handed to trans() and never an empty string - an empty locale reaches the translator as a locale of its own
class ComponentLocaleTest extends TestCase
{
    public function testTheFoldSpeaksTheLanguageItIsGiven(): void
    {
        $html = $this->renderReadmore(['locale' => 'de']);

        $this->assertStringContainsString('label.readmore@de', $html);
        $this->assertStringContainsString('label.readless@de', $html);
    }

    public function testTheFoldLeftWithoutALocaleSpeaksTheVisitorsOwn(): void
    {
        $this->assertStringContainsString('label.readmore@visitor', $this->renderReadmore([]));
    }

    // A Twig component attribute written literally arrives as a string, and an empty one would be asked of the translator as a locale
    public function testAnEmptyLocaleIsReadAsNone(): void
    {
        $this->assertStringContainsString('label.readmore@visitor', $this->renderReadmore(['locale' => '']));
    }

    // Both what is printed here and what assets/js/rating.js is handed, so a vote reads in the language of the page it was cast on
    public function testTheRatingWidgetSpeaksTheLanguageItIsGiven(): void
    {
        $html = $this->renderRating(['locale' => 'de'], ['count' => 37, 'average' => 4.2]);

        $this->assertStringContainsString('data-ui-rating-many-label-value="label.rating_votes_many@de"', $html);
        $this->assertStringContainsString('label.rating_give@de', $html);
        $this->assertStringContainsString('label.rating_votes_many@de</p>', $html);
    }

    public function testTheRatingWidgetLeftWithoutALocaleSpeaksTheVisitorsOwn(): void
    {
        $html = $this->renderRating([], []);

        $this->assertStringContainsString('data-ui-rating-none-label-value="label.rating_none@visitor"', $html);
        $this->assertStringContainsString('label.rating_give@visitor', $html);
    }

    private function renderReadmore(array $context): string
    {
        return $this->twig()->render('components/Text/Readmore.html.twig', $context + ['text' => 'Un texte', 'id' => 1]);
    }

    private function renderRating(array $context, array $rating): string
    {
        $rating += ['ownerType' => 'book', 'ownerId' => 42, 'scale' => 5, 'icon' => 'star', 'average' => 0.0, 'count' => 0];

        $twig = $this->twig();
        // The tally the runtime answers is RatingRuntimeTest's business; what is checked here is the language the markup built from it is written in
        $twig->addFunction(new TwigFunction('ui_rating', static fn (): array => $rating));
        $twig->addFunction(new TwigFunction('path', static fn (string $route, array $parameters = []): string => '/rating/book/42'));

        return $twig->render('components/Rating/Rating.html.twig', $context + ['ownerType' => 'book', 'ownerId' => 42]);
    }

    // The translator names the locale it was asked for back into the key, an unasked one reading as the visitor's own
    private function twig(): Environment
    {
        $translator = new class implements TranslatorInterface {
            public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
            {
                return $id . '@' . ($locale ?? 'visitor');
            }

            public function getLocale(): string
            {
                return 'en';
            }
        };

        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__, 2) . '/templates'));
        $twig->addExtension(new TranslationExtension($translator));
        // The bundle's own filter and not a cast: a cast reads the string "false" as true
        $twig->addFilter(new TwigFilter('to_bool', new BoolExtension()->toBool(...)));

        return $twig;
    }
}
