<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Form;

use c975L\UiBundle\Entity\Review;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Component\Translation\IdentityTranslator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

// Actually renders the score field rather than reading the template as text: the stars are filled by sibling rules over the reversed markup, so the order the radios come out in - and the empty choice staying out of that run - is the whole thing that makes the widget work
class ReviewRatingWidgetRenderTest extends FormIntegrationTestCase
{
    private function renderWidget(): string
    {
        $formDirectory = \dirname(new \ReflectionClass(FormExtension::class)->getFileName(), 2) . '/Resources/views/Form';
        $twig = new Environment(new FilesystemLoader([
            $formDirectory,
            \dirname(__DIR__, 2) . '/templates',
        ]));
        $twig->addExtension(new TranslationExtension(new IdentityTranslator()));
        $twig->addExtension(new FormExtension());

        $theme = 'form/review_rating_theme.html.twig';
        $engine = new TwigRendererEngine(['form_div_layout.html.twig', $theme], $twig);
        // Both keys: the widget calls form_widget() on its children, which Twig resolves against the concrete FormRenderer rather than the interface
        $renderer = new FormRenderer($engine);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRendererInterface::class => static fn (): FormRenderer => $renderer,
            FormRenderer::class => static fn (): FormRenderer => $renderer,
        ]));

        // The very options ReviewType sets on its "rating" field
        $form = $this->factory->create(ChoiceType::class, null, [
            'choices' => array_combine(range(1, Review::SCALE), range(1, Review::SCALE)),
            'placeholder' => 'label.review_form_rating_none',
            'required' => false,
            'expanded' => true,
            'block_prefix' => 'c975l_ui_review_rating',
        ]);

        return $twig->getRuntime(FormRendererInterface::class)->searchAndRenderBlock($form->createView(), 'widget');
    }

    // Reversed markup is what fills every lesser star through a sibling rule, with no javascript: five comes first and one last
    public function testTheStarsAreRenderedFromTheHighestScoreDown(): void
    {
        $html = $this->renderWidget();

        $positions = [];
        foreach (range(1, Review::SCALE) as $value) {
            $position = strpos($html, sprintf('value="%d"', $value));
            $this->assertNotFalse($position, sprintf('the star "%d" is not rendered at all', $value));
            $positions[$value] = $position;
        }
        asort($positions);

        $this->assertSame(array_reverse(range(1, Review::SCALE)), array_keys($positions));
    }

    // The empty choice sits outside the star run: inside it, checking "no score" would fill every star it precedes
    public function testTheEmptyChoiceIsKeptOutOfTheStarRun(): void
    {
        $html = $this->renderWidget();

        $starsBlock = substr($html, (int) strpos($html, 'review-rating-stars'), (int) strpos($html, 'review-rating-none') - (int) strpos($html, 'review-rating-stars'));

        $this->assertStringContainsString('review-rating-none', $html);
        $this->assertStringNotContainsString('value=""', $starsBlock);
    }

    // An icon is a label with nothing in it but the score it stands for, said for a screen reader alone - the shape is the stylesheet's
    public function testEveryStarCarriesItsScoreAsItsAccessibleName(): void
    {
        $html = $this->renderWidget();

        $this->assertSame(Review::SCALE, substr_count($html, 'class="rating-star"'));
        $this->assertSame(Review::SCALE, substr_count($html, 'class="sr-only"'));
        $this->assertStringContainsString('label.rating_give', $html);
    }
}
