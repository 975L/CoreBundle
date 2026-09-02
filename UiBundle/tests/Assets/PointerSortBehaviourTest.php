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

// assets/js/pointer-sort.js driven with real pointer events, which is the only way to drive it at all: it is a gesture and nothing else, and a gesture has no state to read off a file
// Every sortable in the back-office is built on this one module, so what breaks here breaks reordering everywhere at once - and reordering is where a mistake costs somebody the arrangement they had just finished making
#[Group('browser')]
class PointerSortBehaviourTest extends JsCase
{
    private const string MARKUP = '<div id="zone"><div id="item">A</div><button id="inner" type="button">B</button></div><button id="elsewhere" type="button">C</button>';

    // A tap or a click has to keep doing whatever it did before: the whole module is opt-in past a threshold, and a caller told about a gesture that never moved would reorder a list on a plain click
    public function testAGestureUnderTheThresholdIsAClickAndTheCallerHearsNothingOfIt(): void
    {
        $seen = $this->gesture('down(2, 2); move(5, 4); up(5, 4);');

        $this->assertSame([], $seen['hooks'], 'A pointer that barely moved was reported as a drag, so a click on a handle reorders the list.');
        $this->assertFalse($seen['dragging'], 'The dragging class was applied to a gesture that never became a drag.');
    }

    // Past the threshold the gesture is a drag, and it says so on the element, on the body and to the caller
    public function testPastTheThresholdTheDragIsAnnouncedEverywhereItHasToBe(): void
    {
        $seen = $this->gesture('down(2, 2); move(40, 40); up(40, 40);');

        $this->assertSame(['start', 'move', 'drop'], $seen['hooks'], 'The caller is not told about the drag in the order it happens.');
        $this->assertSame([40, 40], $seen['last'], 'The caller is handed the wrong pointer position, so a drop lands somewhere other than under the finger.');
        $this->assertFalse($seen['dragging'], 'The dragged element keeps its dragging class after the drop.');
        $this->assertFalse($seen['body'], 'The body keeps the class that takes the dragged element out of hit-testing, so nothing under the pointer can be hit again.');
    }

    // The class is what takes the dragged element out of hit-testing while it moves - asking what sits under the pointer would otherwise always answer the element being dragged
    public function testTheDraggedElementIsMarkedForAsLongAsTheDragLasts(): void
    {
        $during = $this->gesture('down(2, 2); move(40, 40); report("during"); up(40, 40);');

        $this->assertTrue($during['during']['dragging'], 'The dragged element is not marked while it is being dragged.');
        $this->assertTrue($during['during']['body'], 'The body is not marked while a drag is in progress.');
    }

    // A cancelled gesture is not a drop: a caller treating the two alike commits a reorder the user backed out of
    public function testACancelledGestureIsReportedAsACancelAndNeverAsADrop(): void
    {
        $seen = $this->gesture('down(2, 2); move(40, 40); cancel(40, 40);');

        $this->assertContains('cancel', $seen['hooks']);
        $this->assertNotContains('drop', $seen['hooks'], 'A cancelled gesture was reported as a drop, so a reorder the user abandoned is committed anyway.');
    }

    // A drag started on something clickable must not fire that click on release, the way the native drag never did
    public function testTheClickThatEndsADragIsSwallowed(): void
    {
        $clicks = $this->gesture(
            'let clicks = 0;
             document.getElementById("inner").addEventListener("click", () => { clicks += 1; });
             down(2, 2); move(40, 40); up(40, 40);
             document.getElementById("inner").click();
             report("after", { clicks });'
        );

        $this->assertSame(0, $clicks['after']['clicks'], 'The click ending a drag reaches the element, so dragging an accordion header also opens it.');
    }

    // And it gives up on the next pointerdown too: a pointer that moved produces no click at all in most browsers, and a listener waiting only for one would swallow the user's next real click instead
    public function testTheSuppressionGivesUpRatherThanEatingALaterClick(): void
    {
        $clicks = $this->gesture(
            'let clicks = 0;
             document.getElementById("elsewhere").addEventListener("click", () => { clicks += 1; });
             down(2, 2); move(40, 40); up(40, 40);
             document.dispatchEvent(new PointerEvent("pointerdown", { pointerId: 9, bubbles: true }));
             document.getElementById("elsewhere").click();
             report("after", { clicks });'
        );

        $this->assertSame(1, $clicks['after']['clicks'], 'The suppression outlived the drag and ate a later, unrelated click.');
    }

    // A zone restricted to the mouse must stay unreachable by a finger, or a header covering a whole row makes the page unscrollable from everywhere that header sits
    public function testAZoneRefusingTouchIsNotDraggableWithAFinger(): void
    {
        $seen = $this->gesture('down(2, 2, "touch"); move(40, 40); up(40, 40);', ['touch' => 'false']);

        $this->assertSame([], $seen['hooks'], 'A finger started a drag on a zone that only accepts the mouse.');
        $this->assertFalse($seen['zoneTouch'], 'A mouse-only zone still claims the touch gesture from the browser, which stops the page scrolling there.');
    }

    // The touch-action class is the one thing that has to be applied up front: without it the browser claims the gesture for panning and no pointermove ever arrives
    public function testAZoneAcceptingTouchClaimsTheGestureFromTheBrowser(): void
    {
        $this->assertTrue($this->gesture('report("only");')['zoneTouch'], 'The zone does not claim the touch gesture, so a finger pans the page instead of dragging.');
    }

    // A pointerdown inside the ignored selector is somebody using the control that sits there, not somebody starting a drag
    public function testAGestureStartedInsideTheIgnoredSelectorNeverBecomesADrag(): void
    {
        $seen = $this->gesture('down(2, 2, "mouse", "inner"); move(40, 40); up(40, 40);', ['ignore' => '"#inner"']);

        $this->assertSame([], $seen['hooks'], 'A drag started from inside the ignored selector, so using the control there reorders the list.');
    }

    // One gesture at a time, or a second finger landing on another handle starts a competing drag over the first
    public function testASecondPointerCannotStartACompetingDrag(): void
    {
        $seen = $this->gesture(
            'down(2, 2); move(40, 40);
             document.getElementById("zone").dispatchEvent(new PointerEvent("pointerdown", { pointerId: 7, clientX: 2, clientY: 2, button: 0, bubbles: true, pointerType: "touch" }));
             up(40, 40);'
        );

        $this->assertSame(1, array_count_values($seen['hooks'])['start'] ?? 0, 'A second pointer started its own drag while one was already running.');
    }

    // A right click is not a drag: it opens a context menu, and arming a gesture on it leaves one running with no release to end it
    public function testASecondaryButtonArmsNothing(): void
    {
        $this->assertSame([], $this->gesture('down(2, 2, "mouse", null, 2); move(40, 40); up(40, 40);')['hooks'], 'A right click armed the drag gesture.');
    }

    /**
     * Arms the gesture on the fixture and plays the given pointer script over it.
     */
    private function gesture(string $script, array $options = []): array
    {
        $declared = '';
        foreach ($options as $name => $value) {
            $declared .= sprintf(', %s: %s', $name, $value);
        }

        return (array) $this->observe(
            self::MARKUP,
            [],
            sprintf(
                'const zone = root.querySelector("#zone");
                 const item = root.querySelector("#item");
                 const hooks = [];
                 let last = null;
                 const out = {};
                 mod.sort.addSortGesture(zone, {
                     item,
                     onStart: () => hooks.push("start"),
                     onMove: (element, x, y) => { hooks.push("move"); last = [x, y]; },
                     onDrop: () => hooks.push("drop"),
                     onCancel: () => hooks.push("cancel")%s
                 });

                 const state = () => ({ dragging: item.classList.contains("ui-dragging"), body: document.body.classList.contains("ui-dragging-body") });
                 const report = (name, extra) => { out[name] = { ...state(), ...(extra ?? {}) }; };
                 const fire = (type, x, y, pointerType, target, button) => (target ?? zone).dispatchEvent(new PointerEvent(type, { pointerId: 1, clientX: x, clientY: y, button: button ?? 0, bubbles: true, pointerType: pointerType ?? "mouse" }));
                 const down = (x, y, pointerType, id, button) => fire("pointerdown", x, y, pointerType, id ? root.querySelector("#" + id) : null, button);
                 const move = (x, y) => fire("pointermove", x, y, "mouse", document);
                 const up = (x, y) => fire("pointerup", x, y, "mouse", document);
                 const cancel = (x, y) => fire("pointercancel", x, y, "mouse", document);

                 %s

                 return { hooks, last, ...state(), zoneTouch: zone.classList.contains("ui-sort-zone-touch"), ...out };',
                $declared,
                $script
            ),
            ['modules' => ['sort' => 'pointer-sort']]
        );
    }
}
