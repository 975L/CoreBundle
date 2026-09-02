<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use c975L\UiBundle\Testing\JsCase;
use PHPUnit\Framework\Attributes\Group;

// assets/js/toc.js followed down a page that actually scrolls, with a real IntersectionObserver watching a band across the upper third of the screen
// There is nothing here to read but the scroll position: which entry lights up is decided by where the sections sit against the viewport, so a test that lays nothing out can only check that the words "IntersectionObserver" and "toc-link--current" still appear in the file
#[Group('browser')]
class TocBehaviourTest extends JsCase
{
    // Sections tall enough that only one at a time can cross the band the observer watches
    private const string CSS = '
        .toc-section { height: 1400px; }
        .toc { position: fixed; top: 0; }
    ';

    // The entry of the section being read, and only that one
    public function testTheEntryOfTheSectionBeingReadIsTheOneMarked(): void
    {
        $marked = $this->scrolled([0, 1500, 3000]);

        $this->assertSame(['one', 'two', 'three'], $marked, 'The summary does not follow the reader down the page: the entry marked is not the one whose section is on screen.');
    }

    // The mark is announced and not merely painted: "aria-current" is what says where the reader is to somebody who cannot see the highlight
    public function testTheMarkedEntryIsAnnouncedAndNotOnlyPainted(): void
    {
        $announced = $this->observe(
            $this->markup(),
            ['toc' => 'toc'],
            'window.scrollTo(0, 1500);
             await new Promise((r) => setTimeout(r, 200));
             const links = [...root.querySelectorAll(".toc-link")];

             return {
                 current: links.filter((l) => l.getAttribute("aria-current") === "true").map((l) => l.dataset.tocAnchor),
                 others: links.filter((l) => l.hasAttribute("aria-current")).length,
             };',
            ['css' => self::CSS, 'settle' => 250]
        );

        $this->assertSame(['two'], $announced['current'], 'The section being read is not announced as current.');
        $this->assertSame(1, $announced['others'], 'More than one entry is announced as current at once.');
    }

    // Several sections cross the band while scrolling: the one being read is the first of them in the page order, not the last the observer happened to report
    public function testWhenSeveralSectionsCrossTheBandTheFirstInThePageWins(): void
    {
        $this->assertSame(
            'one',
            $this->observe(
                $this->markup(),
                ['toc' => 'toc'],
                'window.scrollTo(0, 0);
                 await new Promise((r) => setTimeout(r, 200));

                 return root.querySelector(".toc-link--current")?.dataset.tocAnchor ?? null;',
                // Sections short enough that all three sit inside the band at once, and pushed down so they land in it
                ['css' => '.toc-section { height: 30px; } [data-controller=toc] { padding-top: 200px; }', 'settle' => 250]
            ),
            'With several sections crossing the band at once, the entry marked is not the first of them in the page order.'
        );
    }

    // Between two sections nothing crosses the band, and an empty summary reads as broken where the reader has simply not reached the next section yet
    public function testBetweenTwoSectionsTheLastEntryStaysLit(): void
    {
        $this->assertSame(
            'two',
            $this->observe(
                $this->markup('<div style="height: 2000px"></div>'),
                ['toc' => 'toc'],
                'window.scrollTo(0, 1500);
                 await new Promise((r) => setTimeout(r, 200));
                 window.scrollTo(0, 2700);
                 await new Promise((r) => setTimeout(r, 200));

                 return root.querySelector(".toc-link--current")?.dataset.tocAnchor ?? null;',
                ['css' => self::CSS, 'settle' => 250]
            ),
            'The summary went blank between two sections instead of leaving the last entry lit.'
        );
    }

    // Turbo caches the page as it stands, so a snapshot restored later would come back with a mark taken against another scroll position
    public function testDisconnectingLeavesNoMarkForASnapshotToRestore(): void
    {
        $left = $this->observe(
            $this->markup(),
            ['toc' => 'toc'],
            'window.scrollTo(0, 1500);
             await new Promise((r) => setTimeout(r, 200));
             const nav = root.querySelector(".toc");
             const before = !!nav.querySelector(".toc-link--current");
             document.createElement("div").appendChild(nav.closest("[data-controller]"));
             await new Promise((r) => setTimeout(r, 150));

             return { before, after: nav.querySelectorAll(".toc-link--current, [aria-current]").length };',
            ['css' => self::CSS, 'settle' => 250]
        );

        $this->assertTrue($left['before'], 'Nothing was ever marked, so the cleanup being tested proves nothing.');
        $this->assertSame(0, $left['after'], 'A mark is frozen into the cached snapshot and comes back before the reader has scrolled anywhere.');
    }

    /**
     * @param array<int, int> $offsets
     *
     * @return array<int, string|null>
     */
    private function scrolled(array $offsets): array
    {
        $steps = '';
        foreach ($offsets as $offset) {
            $steps .= sprintf('window.scrollTo(0, %d); await new Promise((r) => setTimeout(r, 200)); seen.push(root.querySelector(".toc-link--current")?.dataset.tocAnchor ?? null);', $offset);
        }

        return (array) $this->observe(
            $this->markup(),
            ['toc' => 'toc'],
            'const seen = []; ' . $steps . ' return seen;',
            ['css' => self::CSS, 'settle' => 250]
        );
    }

    private function markup(string $spacer = ''): string
    {
        return sprintf(
            '<div data-controller="toc">
                <nav class="toc">
                    <a class="toc-link" href="#one" data-toc-target="link" data-toc-anchor="one">One</a>
                    <a class="toc-link" href="#two" data-toc-target="link" data-toc-anchor="two">Two</a>
                    <a class="toc-link" href="#three" data-toc-target="link" data-toc-anchor="three">Three</a>
                </nav>
                <div class="toc-section" id="one">One</div>
                <div class="toc-section" id="two">Two</div>
                %s
                <div class="toc-section" id="three">Three</div>
            </div>',
            $spacer
        );
    }
}
