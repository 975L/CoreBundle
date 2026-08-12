<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Templates;

use c975L\UiBundle\Form\Block\ProgressTrackerType;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

// The template clamps on its own, a fixture or an import reaching it without ever passing through ProgressTrackerType
class TrackerSegmentsTest extends TestCase
{
    public function testOneSegmentIsDrawnPerUnitOfTheTotal(): void
    {
        $html = $this->render(['total' => 8, 'completed' => 3]);

        $this->assertSame(8, $this->countSegments($html));
        $this->assertSame(3, substr_count($html, 'tracker-segment--on'));
    }

    // A total of 0 would make "1..total" a descending range, drawing two segments for an empty tracker
    public function testATotalUnderOneIsFlooredToASingleSegment(): void
    {
        $this->assertSame(1, $this->countSegments($this->render(['total' => 0])));
        $this->assertSame(1, $this->countSegments($this->render(['total' => -5])));
    }

    // The same ceiling the form caps its two figures at, so a stored value lands on the same row whichever way it got there
    public function testATotalOverTheCeilingIsCapped(): void
    {
        $html = $this->render(['total' => 500]);

        $this->assertSame(ProgressTrackerType::MAX_SEGMENTS, $this->countSegments($html));
    }

    public function testACountOutsideTheTotalIsClampedToIt(): void
    {
        $this->assertSame(6, substr_count($this->render(['total' => 6, 'completed' => 40]), 'tracker-segment--on'));
        $this->assertSame(0, substr_count($this->render(['total' => 6, 'completed' => -2]), 'tracker-segment--on'));
    }

    // The figure reads the clamped values, not the ones passed in: "40 / 06" would describe a row of six segments
    public function testTheFigureReadsTheClampedValuesZeroPadded(): void
    {
        $this->assertStringContainsString('06 / 06', $this->render(['total' => 6, 'completed' => 40]));
        $this->assertStringContainsString('03 / 12', $this->render(['total' => 12, 'completed' => 3]));
    }

    // The count carries the information, so a screen reader is told the figure once rather than walked through sixty spans
    public function testTheSegmentRowIsHiddenFromAssistiveTechnology(): void
    {
        $html = $this->render(['total' => 4, 'completed' => 2]);

        $this->assertStringContainsString('<div class="tracker-segments" aria-hidden="true">', $html);
    }

    // Everything around the count is trimming an editor may skip, and an empty one writes no element at all
    public function testTheTrimmingIsOnlyWrittenWhenItIsSet(): void
    {
        $bare = $this->render(['total' => 3, 'completed' => 1]);

        $this->assertStringNotContainsString('tracker-eyebrow', $bare);
        $this->assertStringNotContainsString('tracker-title', $bare);
        $this->assertStringNotContainsString('tracker-note', $bare);

        $full = $this->render(['total' => 3, 'completed' => 1, 'eyebrow' => 'Collection', 'title' => 'Tomes parus', 'note' => 'Le quatrième est en préparation.']);

        $this->assertStringContainsString('>Collection<', $full);
        $this->assertStringContainsString('>Tomes parus<', $full);
        $this->assertStringContainsString('Le quatrième est en préparation.', $full);
    }

    private function countSegments(string $html): int
    {
        return substr_count($html, '<span class="tracker-segment ');
    }

    private function render(array $context): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2) . '/templates'));

        return $twig->render('components/Progress/Tracker.html.twig', $context);
    }
}
