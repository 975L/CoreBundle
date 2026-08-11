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
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// Both head fields are optional on this kind, which is what decides the element the band is rendered as
class FeatureBarHeadTest extends TestCase
{
    private const array ITEMS = [['title' => 'Premier point', 'text' => 'une précision']];

    // A headingless <section> is invalid HTML, and a band standing on its items alone is the ordinary case
    public function testABandWithNoHeadRendersAsADiv(): void
    {
        $html = $this->render(['items' => self::ITEMS]);

        $this->assertStringContainsString('<div class="feature-bar"', $html);
        $this->assertStringNotContainsString('<section', $html);
        $this->assertStringNotContainsString('section-head', $html);
    }

    public function testATitleTurnsTheBandIntoASectionAndPrintsTheHeadInAWrap(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'eyebrow' => 'Surtitre', 'title' => 'Le titre']);

        $this->assertStringContainsString('<section class="feature-bar"', $html);
        $this->assertStringContainsString('</section>', $html);
        $this->assertStringContainsString('<div class="section-wrap">', $html);
        $this->assertStringContainsString('<p class="section-eyebrow">Surtitre</p>', $html);
        $this->assertStringContainsString('<h2 class="section-title">Le titre</h2>', $html);
    }

    // Shared with every other kind taking the same head: with no title the eyebrow is the section's heading
    public function testAnEyebrowAloneBecomesTheSectionHeading(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'eyebrow' => 'Surtitre']);

        $this->assertStringContainsString('<section class="feature-bar"', $html);
        $this->assertStringContainsString('<h2 class="section-eyebrow">Surtitre</h2>', $html);
    }

    // The head is the only part taking the measure, the grid spanning the band edge to edge as it always did
    public function testTheGridStaysOutsideTheWrap(): void
    {
        $html = $this->render(['items' => self::ITEMS, 'title' => 'Le titre']);

        $this->assertMatchesRegularExpression('#</div>\s*<div class="feature-bar__grid"#', $html);
    }

    private function render(array $context): string
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $loader->addPath(dirname(__DIR__, 2) . '/templates', 'c975LUi');

        return new Environment($loader)->render('components/Feature/Bar.html.twig', $context);
    }
}
