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

// The scale is clamped the way Progress:Tracker clamps its total, both ends of it: "1..max" counts backwards below 1, and a scale nobody caps pushes its row off a phone
class RatingScaleTest extends TestCase
{
    // A scale of 0 would make "1..0" a descending range, printing two stars for an empty scale
    public function testAScaleUnderOneIsFlooredToASingleStar(): void
    {
        $html = $this->render(['max' => 0]);

        $this->assertSame(1, $this->countStars($html));
    }

    public function testANegativeScaleIsFlooredToo(): void
    {
        $html = $this->render(['max' => -3]);

        $this->assertSame(1, $this->countStars($html));
    }

    // Ten is the ceiling, so no stored value can make the row overflow its line
    public function testAScaleOverTenIsCappedAtTenStars(): void
    {
        $html = $this->render(['max' => 500]);

        $this->assertSame(10, $this->countStars($html));
    }

    // The clamp only touches what falls outside it - the ordinary scale, and the default one, stay untouched
    public function testAScaleInsideTheBoundsIsLeftAsItIs(): void
    {
        $this->assertSame(5, $this->countStars($this->render(['max' => 5, 'value' => 4])));
        $this->assertSame(7, $this->countStars($this->render(['max' => 7])));
        $this->assertSame(5, $this->countStars($this->render([])));
    }

    // The label reads the clamped scale, not the one passed in: "4/500" would describe a row of ten stars
    public function testTheAccessibleNameReadsTheClampedScale(): void
    {
        $html = $this->render(['max' => 500, 'value' => 4]);

        $this->assertStringContainsString('aria-label="4/10"', $html);
    }

    private function countStars(string $html): int
    {
        return substr_count($html, '<span class="rating-star');
    }

    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        return $twig->render('components/Progress/Rating.html.twig', $context);
    }
}
