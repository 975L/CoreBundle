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

// Reordering rows in the back-office runs on Pointer Events, not on the native HTML5 drag and drop it used to: no touch browser can be counted on to emit "dragstart" at all, so a finger could never move a block. Nothing here is provable from a unit test - it is a browser gesture - so what is locked is that the pieces making it work at the finger stay in place, the same way SliderSwipeTest guards the freeflow swipe.
class PointerSortTest extends TestCase
{
    private const CSS = ['/public/css/management.css', '/public/css/management.min.css'];

    // The whole point of the shared module: one gesture layer, reused by every sortable rather than re-hand-rolled in each
    public function testGestureLayerIsBuiltOnPointerEvents(): void
    {
        $js = $this->js('pointer-sort.js');

        $this->assertStringContainsString('export function addSortGesture', $js, 'The gesture layer is no longer exported, so no other sortable can reuse it.');

        foreach (['pointerdown', 'pointermove', 'pointerup', 'pointercancel'] as $event) {
            $this->assertStringContainsString($event, $js, sprintf('No "%s" handling left, so the gesture is incomplete on touch.', $event));
        }

        $this->assertDoesNotMatchRegularExpression('/addEventListener\([\'"]drag/', $js, 'The native drag API is back, and touch browsers do not fire it.');
    }

    // Without it the browser claims the gesture for panning and not one pointermove ever arrives. Carried by a class, not by an inline style: EasyAdmin's layout puts a nonce on style-src, which makes 'unsafe-inline' a no-op, and a nonce never authorizes a style written from JS
    public function testTouchZonesGiveUpBrowserPanning(): void
    {
        $this->assertStringContainsString("classList.add('ui-sort-zone-touch')", $this->js('pointer-sort.js'), 'A touch-enabled zone no longer opts out of panning, so a finger scrolls the page instead of dragging.');
        $this->assertStringContainsString('.ui-sort-zone-touch{touch-action:none;}', $this->css('/public/css/management.css'), 'The class is set but nothing declares touch-action, so the finger still pans the page.');
    }

    // A native drag image was transparent to hit-testing; the dragged row itself is not, and would answer every "what sits under the pointer" with itself or with a collection nested inside it. Declared on .ui-dragging, the class the gesture already toggles, for the same nonce reason as above
    public function testDraggedElementIsTakenOutOfHitTesting(): void
    {
        $this->assertStringContainsString("classList.add('ui-dragging')", $this->js('pointer-sort.js'), 'The dragged element no longer carries the class its styling hangs on.');
        $this->assertStringContainsString('.ui-dragging{pointer-events:none;}', $this->css('/public/css/management.css'), 'The dragged element still takes hits, so it is dropped onto itself.');
    }

    // The browser scrolled the page near the viewport edges on its own during a native drag; a collection taller than the screen is otherwise only sortable as far as the screen shows
    public function testDragScrollsThePageAtTheViewportEdges(): void
    {
        $js = $this->js('pointer-sort.js');

        $this->assertStringContainsString('window.scrollBy', $js, 'Nothing scrolls the page during a drag.');
        $this->assertStringContainsString('requestAnimationFrame(autoScroll)', $js, 'The edge scroll is not driven per frame, so it stops as soon as the pointer does.');
    }

    // A drag started on a button or on an accordion header must not also fire that click when it ends
    public function testTheClickFollowingADragIsSwallowed(): void
    {
        $js = $this->js('pointer-sort.js');

        $this->assertStringContainsString('suppressNextClick', $js, 'The click a drag leaves behind is no longer suppressed, so dropping a row also toggles it open.');
        $this->assertStringContainsString("addEventListener('pointerdown', stop, true)", $js, 'The suppressor never gives up on its own - a drag producing no click at all would leave it to eat the next real one.');
    }

    // The controller keeps the ordering logic and hands the gesture over
    public function testBlockSortableUsesTheSharedGesture(): void
    {
        $js = $this->js('ea-sortable.js');

        $this->assertStringContainsString('addSortGesture', $js, 'The block sortable no longer goes through the shared gesture layer.');
        $this->assertStringContainsString('elementFromPoint', $js, 'Nothing hit-tests the field under the pointer, which "dragover" used to give for free.');
        $this->assertStringNotContainsString('draggable', $js, 'The native "draggable" attribute is back, and a touch never sets it in time.');
    }

    // Offering the whole header bar to a finger takes "touch-action: none" over all of it, and the page would stop scrolling from everywhere a block header sits
    public function testGrabbingARowByItsHeaderStaysAMouseGesture(): void
    {
        $this->assertStringContainsString('touch: false', $this->js('ea-sortable.js'), 'The row header accepts touch, so the back-office can no longer be scrolled from a block header.');
    }

    // Which leaves the handle as the one grab point at the finger, and the bare icon is well under a comfortable touch target
    public function testMoveHandleIsEnlargedOnACoarsePointer(): void
    {
        foreach (self::CSS as $file) {
            $css = $this->css($file);

            $this->assertMatchesRegularExpression('/@media ?\(pointer:coarse\)/', $css, $file . ' has no coarse-pointer rule at all.');
            $this->assertMatchesRegularExpression('/\.ui-sort-handle\{padding-block:0?\.5rem;padding-inline:0?\.75rem;?\}/', $css, $file . ' no longer widens the move handle for a finger.');

            // Nothing but the declaration order makes it win: .ui-toolbar-btn sets the padding of every toolbar button at the very same specificity, so moving the coarse-pointer block back above it would silently drop the touch target without breaking the assertion above
            $handle = (int) preg_match('/@media ?\(pointer:coarse\)/', $css, $matches, PREG_OFFSET_CAPTURE) ? $matches[0][1] : -1;
            $this->assertGreaterThan(strpos($css, '.ui-toolbar-btn{'), $handle, $file . ' declares the coarse-pointer rule before .ui-toolbar-btn, whose padding then wins on the cascade.');
        }
    }

    private function js(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/' . $file);
    }

    // Same shape whichever of the two stylesheets it comes from - only the space around the punctuation differs
    private function css(string $file): string
    {
        $css = (string) preg_replace('/\s+/', ' ', (string) file_get_contents(dirname(__DIR__, 2) . $file));

        return (string) preg_replace('#\s*([:;{},/])\s*#', '$1', $css);
    }
}
