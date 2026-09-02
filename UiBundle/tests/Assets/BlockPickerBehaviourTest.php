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

// assets/js/block-picker.js run over the kind row EasyAdmin renders, palette opened and a kind chosen from it
// The picker hides the <select> it replaces, so everything it fails to do it fails to do over a control the editor can no longer reach: a trigger that never gets built leaves a row with no way to choose a kind, and a choice that dispatches no change event leaves the sub-form showing the previous kind's fields
#[Group('browser')]
class BlockPickerBehaviourTest extends JsCase
{
    // Two categories and a chosen kind, as BlockType renders them
    private const string ROW = '<div data-kind-row>
        <label for="kind">Kind</label>
        <select id="kind">
            <option value="">Choose a kind</option>
            <optgroup label="Text">
                <option value="text" data-label="Text">Text — a paragraph</option>
                <option value="title" data-label="Title">Title — a heading</option>
            </optgroup>
            <optgroup label="Media">
                <option value="map" data-label="Map">Map — places on a map</option>
            </optgroup>
        </select>
    </div>';

    // The select goes on holding the posted value, so it is hidden and never removed - and it is only hidden once the trigger is actually in the page, or the row is left with no control at all
    public function testTheRowGetsATriggerAndOnlyThenHasItsSelectHidden(): void
    {
        $row = $this->picker(
            'const row = root.querySelector("[data-kind-row]");
             const trigger = row.querySelector(".ui-block-picker-trigger");

             return {
                 built: !!trigger,
                 type: trigger?.type ?? null,
                 hidden: row.classList.contains("ui-block-picker-on"),
                 selectStillThere: !!row.querySelector("select"),
                 labelled: trigger?.getAttribute("aria-labelledby") === row.querySelector("label").id,
             };'
        );

        $this->assertTrue($row['built'], 'No trigger was built, so the row offers no way to choose a kind.');
        $this->assertSame('button', $row['type'], 'The trigger is a submit button, so the first tap posts the whole block form.');
        $this->assertTrue($row['hidden'], 'The select is not hidden, leaving the row with two controls for one field.');
        $this->assertTrue($row['selectStillThere'], 'The select was removed, so the posted form carries no kind at all.');
        $this->assertTrue($row['labelled'], 'The trigger is not named by the row\'s own label, so it is announced as a bare button.');
    }

    // The trigger stands for the field, so it has to show what is chosen: the option's short label, and the silhouette of that kind
    public function testTheTriggerShowsTheChosenKindAndItsSilhouette(): void
    {
        $shown = $this->picker(
            'const trigger = root.querySelector(".ui-block-picker-trigger");

             return {
                 label: trigger.querySelector(".ui-block-picker-trigger__label").textContent.trim(),
                 thumb: trigger.querySelector(".ui-block-thumb")?.className ?? null,
                 parts: trigger.querySelectorAll(".ui-block-thumb b").length,
                 empty: trigger.classList.contains("ui-block-picker-trigger--empty"),
             };',
            'title'
        );

        $this->assertSame('Title', $shown['label'], 'The trigger shows the option\'s full text instead of its short label.');
        $this->assertStringContainsString('ui-block-thumb--title', (string) $shown['thumb'], 'The silhouette does not carry the chosen kind, so every block looks alike in the palette.');
        $this->assertSame(5, $shown['parts'], 'The silhouette is not drawn from the five parts the stylesheet arranges per kind.');
        $this->assertFalse($shown['empty']);
    }

    // Nothing chosen yet: the placeholder is already translated by Symfony, so the empty state costs no string of its own
    public function testAnUnsetRowFallsBackOnItsOwnPlaceholder(): void
    {
        $empty = $this->picker(
            'const trigger = root.querySelector(".ui-block-picker-trigger");

             return { label: trigger.querySelector(".ui-block-picker-trigger__label").textContent.trim(), empty: trigger.classList.contains("ui-block-picker-trigger--empty"), thumb: !!trigger.querySelector(".ui-block-thumb") };',
            ''
        );

        $this->assertSame('Choose a kind', $empty['label'], 'An unset row shows nothing at all on its trigger.');
        $this->assertTrue($empty['empty'], 'An unset trigger is not marked as such, so it looks like a chosen kind.');
        $this->assertFalse($empty['thumb'], 'An unset trigger shows a silhouette of nothing.');
    }

    // The palette is built from the row's own select: which kinds a row may take depends on where it sits, and a list of its own would offer kinds the context refuses
    public function testThePaletteIsBuiltFromTheRowsOwnChoicesGroupedAsTheyAre(): void
    {
        $palette = $this->picker(
            'root.querySelector(".ui-block-picker-trigger").click();
             await new Promise((r) => setTimeout(r, 50));
             const dialog = document.querySelector(".ui-block-picker");

             return {
                 open: dialog?.open ?? false,
                 categories: [...dialog.querySelectorAll(".ui-block-picker__category")].map((c) => c.textContent),
                 kinds: [...dialog.querySelectorAll(".ui-block-kind")].map((k) => k.dataset.kind),
                 pressed: [...dialog.querySelectorAll(".ui-block-kind[aria-pressed=true]")].map((k) => k.dataset.kind),
             };',
            'title'
        );

        $this->assertTrue($palette['open'], 'The palette never opened.');
        $this->assertSame(['Text', 'Media'], $palette['categories'], 'The categories of the row\'s own select are not the ones the palette shows.');
        $this->assertSame(['text', 'title', 'map'], $palette['kinds'], 'The palette offers other kinds than the row accepts - the placeholder option leaking in, or a group dropped.');
        $this->assertSame(['title'], $palette['pressed'], 'The kind already chosen is not marked as pressed, so nothing says what the row currently holds.');
    }

    // The one change event block.js needs, dispatched once: letting a wrapper fire its own on top would load the kind's sub-form twice
    public function testChoosingAKindSetsTheSelectAndAnnouncesItExactlyOnce(): void
    {
        $chosen = $this->picker(
            'const select = root.querySelector("select");
             let changes = 0;
             select.addEventListener("change", () => { changes += 1; });
             root.querySelector(".ui-block-picker-trigger").click();
             await new Promise((r) => setTimeout(r, 50));
             document.querySelector(".ui-block-kind[data-kind=map]").click();
             await new Promise((r) => setTimeout(r, 50));

             return {
                 value: select.value,
                 changes,
                 open: document.querySelector(".ui-block-picker")?.open ?? false,
                 trigger: root.querySelector(".ui-block-picker-trigger__label").textContent.trim(),
             };',
            'title'
        );

        $this->assertSame('map', $chosen['value'], 'Choosing a kind did not set the select, so the posted form still carries the previous one.');
        $this->assertSame(1, $chosen['changes'], 'The change was announced a number of times other than once, which loads the kind\'s sub-form twice or not at all.');
        $this->assertFalse($chosen['open'], 'The palette stays open over the sub-form it just asked for.');
        $this->assertSame('Map', $chosen['trigger'], 'The trigger still shows the kind that was replaced.');
    }

    // A context down to a single category renders no optgroup at all, and an empty sheet would be a row that cannot be given a kind
    public function testASelectWithoutGroupsStillFillsThePalette(): void
    {
        $kinds = $this->picker(
            'root.querySelector(".ui-block-picker-trigger").click();
             await new Promise((r) => setTimeout(r, 50));

             return [...document.querySelectorAll(".ui-block-kind")].map((k) => k.dataset.kind);',
            'text',
            '<div data-kind-row><label for="kind">Kind</label><select id="kind"><option value="">Choose</option><option value="text" data-label="Text">Text</option></select></div>'
        );

        $this->assertSame(['text'], $kinds, 'A select with no groups fills an empty palette, leaving the row with no kind to choose.');
    }

    // A row cloned into the page long after load - EasyAdmin's collection script, or a container's slots arriving with the sub-form the picker itself just asked for
    public function testARowAddedAfterTheFactIsEnhancedToo(): void
    {
        $this->assertTrue(
            (bool) $this->picker(
                'const added = document.createElement("div");
                 added.innerHTML = ' . json_encode(self::ROW) . ';
                 root.appendChild(added);
                 document.dispatchEvent(new CustomEvent("ea.collection.item-added", { detail: { newElement: added } }));
                 await new Promise((r) => setTimeout(r, 50));

                 return !!added.querySelector(".ui-block-picker-trigger");'
            ),
            'A row added to the collection after load never gets its picker, so a newly added block cannot be given a kind.'
        );
    }

    // A kind changed anywhere else - a duplicated row, a browser restoring a form - still has to show on the trigger
    public function testAKindChangedElsewhereShowsOnTheTrigger(): void
    {
        $this->assertSame(
            'Map',
            $this->picker(
                'const select = root.querySelector("select");
                 select.value = "map";
                 select.dispatchEvent(new Event("change", { bubbles: true }));
                 await new Promise((r) => setTimeout(r, 50));

                 return root.querySelector(".ui-block-picker-trigger__label").textContent.trim();',
                'text'
            ),
            'A kind set from outside the palette is not shown on the trigger, so the row displays one kind and posts another.'
        );
    }

    private function picker(string $probe, string $selected = 'text', ?string $row = null): mixed
    {
        $html = $row ?? self::ROW;

        if ('' !== $selected) {
            $html = str_replace(sprintf('<option value="%s"', $selected), sprintf('<option selected value="%s"', $selected), $html);
        }

        return $this->observe(
            $html,
            [],
            // The module enhances the whole document once, when it is first imported: this is the event a sub-form arriving later fires, and the one path that reaches rows mounted after that
            'document.dispatchEvent(new CustomEvent("c975l:block-data-loaded"));
             await new Promise((r) => setTimeout(r, 30));
             ' . $probe,
            ['modules' => ['picker' => 'block-picker'], 'keepBody' => true]
        );
    }
}
