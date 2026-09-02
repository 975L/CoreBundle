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

// assets/js/ea-sortable.js reordered by an actual drag: pointer events over rows that have a real height, hit-tested at real coordinates
// Nothing about this can be checked without laying the page out. The controller decides where a row lands by asking the browser what sits under the pointer and by measuring every row against it - two things an emulated DOM answers null and zero to, which is a reorder that always drops at the end and a suite that never notices
#[Group('browser')]
class EaSortableBehaviourTest extends JsCase
{
    // What sass/management/_block-collection.scss declares for a drag, reduced to what the reorder depends on: without the first rule the browser answers the dragged row itself to every hit test, and nothing can be dropped anywhere
    private const string CSS = '
        .ui-dragging { pointer-events: none; }
        .field-collection-item { height: 40px; background: #eee; margin: 0; }
        .ea-form-collection-items { display: block; }
    ';

    // A collection with no position field is somebody else's, and arming it would offer a reorder that is never saved
    public function testOnlyACollectionCarryingAPositionFieldBecomesSortable(): void
    {
        $armed = $this->observe(
            $this->mounted($this->field('sortable', 3) . $this->field('plain', 2, false)),
            ['eaSortable' => 'ea-sortable'],
            'return {
                 sortable: root.querySelectorAll("#sortable .ui-sort-handle").length,
                 plain: root.querySelectorAll("#plain .ui-sort-handle").length,
             };'
        );

        $this->assertSame(3, $armed['sortable'], 'A sortable collection got no move handle, so its rows cannot be reordered at all.');
        $this->assertSame(0, $armed['plain'], 'A collection with no position field was armed, offering a reorder nothing will ever save.');
    }

    // The header is grabbable too, but only with a mouse: giving a finger the whole bar takes touch-action over it, and the page stops scrolling from everywhere a block header sits
    public function testTheHeaderIsGrabbableWithAMouseAndNeverClaimsTheFingersGesture(): void
    {
        $header = $this->observe(
            $this->mounted($this->field('sortable', 2)),
            ['eaSortable' => 'ea-sortable'],
            'const head = root.querySelector(".accordion-header");

             return { grabbable: head.classList.contains("ui-sort-grabbable"), claimsTouch: head.classList.contains("ui-sort-zone-touch"), handleTakesTouch: root.querySelector(".ui-sort-handle").classList.contains("ui-sort-zone-touch") };'
        );

        $this->assertTrue($header['grabbable'], 'The row header cannot be grabbed, leaving only the small icon to drag from.');
        $this->assertFalse($header['claimsTouch'], 'The header claims the touch gesture, which stops the page scrolling from every block header on it.');
        $this->assertTrue($header['handleTakesTouch'], 'The handle does not claim the touch gesture, so a finger pans the page instead of dragging the row.');
    }

    // The reorder itself, dragged from the last row up over the first
    public function testDraggingARowAboveAnotherReordersItAndRenumbersThePositions(): void
    {
        $state = $this->drag('sortable', 2, 0, 'return { order: labels(), positions: positions() };');

        $this->assertSame(['C', 'A', 'B'], $state['order'], 'The dragged row did not land above the one it was dropped on.');
        $this->assertSame(['0', '1', '2'], $state['positions'], 'The positions were not renumbered after the drop, so the order saved is not the order on screen.');
    }

    // A gesture the browser takes back - a system gesture, a context menu - puts the row back where it was picked up rather than leaving it where the pointer had pushed it
    public function testACancelledDragPutsTheRowBackWhereItWasPickedUp(): void
    {
        $state = $this->drag('sortable', 2, 0, 'return { order: labels() };', true);

        $this->assertSame(['A', 'B', 'C'], $state['order'], 'A cancelled drag left the row wherever the pointer had pushed it.');
    }

    // The row says it is being dragged while it moves, and says nothing once it has landed
    public function testTheDraggedRowIsMarkedForTheDurationAndCleanedUpAfterwards(): void
    {
        $state = $this->drag('sortable', 2, 0, 'return { during: window.__during, after: root.querySelector("#row-2").classList.contains("ui-dragging-visual") };');

        $this->assertTrue($state['during'], 'The dragged row is not marked while it moves, so nothing on screen follows the gesture.');
        $this->assertFalse($state['after'], 'The dragged row keeps its drag styling after the drop.');
    }

    // Two fields exchange rows only when both name the same group; a field naming none stays single-field, which is every sortable that never asked for more
    public function testOnlyFieldsOfTheSameGroupAreOfferedAsDropTargets(): void
    {
        $highlighted = $this->observe(
            $this->mounted(
                $this->field('a', 2, true, 'blocks')
                . $this->field('b', 1, true, 'blocks')
                . $this->field('c', 1, true, 'other')
                . $this->field('d', 1)
            ),
            ['eaSortable' => 'ea-sortable'],
            'const handle = root.querySelector("#a .ui-sort-handle");
             const box = handle.getBoundingClientRect();
             const at = (type, x, y) => (type === "pointerdown" ? handle : document).dispatchEvent(new PointerEvent(type, { pointerId: 1, clientX: x, clientY: y, button: 0, bubbles: true, pointerType: "mouse" }));
             at("pointerdown", box.x + 2, box.y + 2);
             at("pointermove", box.x + 40, box.y + 40);
             const outlined = [...root.querySelectorAll(".ui-drop-target")].map((el) => el.closest("[data-ea-collection-field]").id);
             at("pointerup", box.x + 40, box.y + 40);

             return outlined;',
            ['css' => self::CSS]
        );

        $this->assertSame(['b'], $highlighted, 'The fields offered as drop targets are not the ones sharing the dragged row\'s group.');
    }

    // A restricted row may be reordered but not removed - the move handle is untouched, only the delete button goes
    public function testARestrictedRowKeepsItsHandleAndLosesItsDeleteButton(): void
    {
        $state = $this->observe(
            $this->mounted(str_replace(
                '<!--marker-->',
                '<input type="checkbox" class="ui-field-restricted" checked><button type="button" class="field-collection-delete-button"></button>',
                $this->field('sortable', 1)
            )),
            ['eaSortable' => 'ea-sortable'],
            'return {
                 handle: !!root.querySelector(".ui-sort-handle"),
                 deletable: !root.querySelector(".field-collection-delete-button").classList.contains("ui-hidden"),
             };'
        );

        $this->assertTrue($state['handle'], 'A restricted row lost its move handle, so it can no longer be reordered.');
        $this->assertFalse($state['deletable'], 'A restricted row still offers its delete button.');
    }

    // The reorder leans on one stylesheet rule to work at all: the dragged row has to be out of hit-testing, or the browser answers it to every question about what sits under the pointer
    public function testTheStylesheetStillTakesTheDraggedRowOutOfHitTesting(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.ui-dragging\s*\{[^}]*pointer-events\s*:\s*none/',
            $this->shipped('sass/management/_block-collection.scss'),
            'The rule taking the dragged row out of hit-testing is gone, so every drop lands back on the row being dragged.'
        );
    }

    // Drags the row at $from over the row at $to and reports what the probe asks for
    private function drag(string $field, int $from, int $to, string $probe, bool $cancel = false): array
    {
        return (array) $this->observe(
            $this->mounted($this->field($field, 3)),
            ['eaSortable' => 'ea-sortable'],
            sprintf(
                'const rows = () => [...root.querySelectorAll(".field-collection-item")];
                 const labels = () => rows().map((r) => r.dataset.label);
                 const positions = () => rows().map((r) => r.querySelector("[name$=\'[position]\']").value);
                 const handle = root.querySelector("#row-%1$d .ui-sort-handle");
                 const target = root.querySelector("#row-%2$d").getBoundingClientRect();
                 const start = handle.getBoundingClientRect();
                 const at = (type, x, y, on) => (on ?? document).dispatchEvent(new PointerEvent(type, { pointerId: 1, clientX: x, clientY: y, button: 0, bubbles: true, pointerType: "mouse" }));

                 at("pointerdown", start.x + 2, start.y + 2, handle);
                 at("pointermove", start.x + 2, start.y + 20);
                 window.__during = root.querySelector("#row-%1$d").classList.contains("ui-dragging-visual");
                 at("pointermove", target.x + 5, target.y + 2);
                 at("%3$s", target.x + 5, target.y + 2);
                 await new Promise((r) => setTimeout(r, 50));

                 %4$s',
                $from,
                $to,
                $cancel ? 'pointercancel' : 'pointerup',
                $probe
            ),
            ['css' => self::CSS]
        );
    }

    // The controller is mounted on <body> by controllers-admin.js, so here it wraps the whole fixture
    private function mounted(string $html): string
    {
        return '<div data-controller="eaSortable" data-ui-move-url="/move">' . $html . '</div>';
    }

    // A collection field as EasyAdmin renders one: an items container, rows carrying an accordion header and a position input
    private function field(string $id, int $rows, bool $positions = true, ?string $group = null): string
    {
        $items = '';
        for ($index = 0; $index < $rows; ++$index) {
            $items .= sprintf(
                '<div class="field-collection-item" id="row-%d" data-label="%s"><div class="accordion-header"></div><!--marker-->%s</div>',
                $index,
                \chr(65 + $index),
                $positions ? sprintf('<input name="f[%s][%d][position]" value="%d">', $id, $index, $index) : ''
            );
        }

        return sprintf(
            '<div id="%s" data-ea-collection-field%s data-prototype="%s"><div class="ea-form-collection-items">%s</div></div>',
            $id,
            null !== $group ? sprintf(' data-ui-sort-group="%s"', $group) : '',
            $positions ? 'name=&quot;f[__name__][position]&quot;' : '',
            $items
        );
    }
}
