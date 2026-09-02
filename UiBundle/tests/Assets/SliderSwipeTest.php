<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\UiBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// A freeflow slider is scrolled by hand rather than by the browser's own fling, which carried several slides at once and left the dots behind; the pieces that make that work live half in the stylesheet, half in the controller, and neither half works alone
class SliderSwipeTest extends TestCase
{
    private const array CSS = ['/public/css/styles.css', '/public/css/styles.min.css'];

    // The incoming slide is centered, not left-aligned, which the scroll math in displaySlideFreeflow() targets
    public function testFreeflowSlidesSnapCentered(): void
    {
        foreach (self::CSS as $file) {
            $css = $this->css($file);

            $this->assertStringContainsString('scroll-snap-align:center', $css, $file . ' aligns freeflow slides anywhere but centered.');
            $this->assertStringNotContainsString('scroll-snap-align:start', $css, $file . ' still start-aligns a slide.');
        }
    }

    // Side padding derived from one slide's own width, else the first and last slides can never reach the center
    public function testFreeflowListPaddingCentersTheEndSlides(): void
    {
        foreach (self::CSS as $file) {
            $this->assertStringContainsString(
                'padding-inline:calc(50% - var(--slider-freeflow-item)/2)',
                $this->css($file),
                $file . ' does not carve the list padding out of a slide\'s width, so slide one cannot be centered.'
            );
        }
    }

    // Snapping and smooth scrolling both fight the per-frame scrollLeft a drag writes
    public function testDraggingClassSuspendsSnapAndSmoothScroll(): void
    {
        foreach (self::CSS as $file) {
            $css = $this->css($file);
            $rule = $this->rule($css, '.slider-freeflow .slider-list.slider-dragging');

            $this->assertStringContainsString('scroll-snap-type:none', $rule, $file . ' keeps snapping while the list is dragged.');
            $this->assertStringContainsString('scroll-behavior:auto', $rule, $file . ' keeps smooth scrolling while the list is dragged.');
        }
    }

    // Horizontal panning is the controller's, vertical panning stays the page's
    public function testFreeflowListLeavesVerticalPanningToTheBrowser(): void
    {
        foreach (self::CSS as $file) {
            $this->assertStringContainsString('touch-action:pan-y', $this->css($file), $file . ' does not hand horizontal panning to the controller.');
        }
    }

    // The controller's own half of it
    public function testControllerDrivesTheFreeflowDragItself(): void
    {
        $js = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/slider.js');

        $this->assertStringContainsString('setupFreeflowGestures', $js, 'The freeflow layout no longer handles its own swipe.');
        $this->assertStringContainsString('slider-dragging', $js, 'Nothing suspends snapping for the duration of a drag.');
        $this->assertStringContainsString('centeredSlideIndex', $js, 'Nothing reads back which slide a hand-driven scroll landed on, so the dots cannot follow it.');
        $this->assertMatchesRegularExpression('/scroll["\']\s*,/', $js, 'No scroll listener, so a swipe or a trackpad leaves the dots behind.');
        $this->assertStringContainsString('startIndex + step', $js, 'A swipe must move exactly one slide from where it started.');
    }

    // Same shape whichever of the two stylesheets it comes from - only the space around the punctuation differs
    private function css(string $file): string
    {
        $css = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(dirname(__DIR__, 2) . $file));

        return (string) preg_replace('#\s*([:;{},/])\s*#', '$1', $css);
    }

    private function rule(string $css, string $selector): string
    {
        $normalized = (string) preg_replace('/\s+/', ' ', $selector);

        $this->assertMatchesRegularExpression('/' . preg_quote($normalized, '/') . '\{([^}]*)\}/', $css, sprintf('"%s" is not declared at all.', $selector));
        preg_match('/' . preg_quote($normalized, '/') . '\{([^}]*)\}/', $css, $matches);

        return $matches[1];
    }
}
