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

// assets/js/block-duplicate.js against EasyAdmin's collection markup, with the "add" button appending a row the way EasyAdmin's own does
// The controller is mounted on <body>, so it sees every collection in the whole admin: what it must do to a block row, a media row and a nested item, and what it must leave entirely alone, is decided by reading the markup - which is exactly the kind of decision no test reading the file as text can check. And when it gets one wrong it does not fail loudly, it copies the wrong values into a row somebody then saves
#[Group('browser')]
class BlockDuplicateBehaviourTest extends JsCase
{
    // A row is a block (it has a kind selector), a media (it has a file input), a nested item (it sits in a block's data form), or none of those - and the last kind belongs to somebody else's collection elsewhere in the admin
    public function testOnlyTheRowsThisControllerOwnsGetADuplicateButton(): void
    {
        $owned = $this->observe(
            $this->mounted(
                $this->field('block', '<div data-kind-row><select><option value="text" selected>Text</option></select></div>')
                . $this->field('media', '<input type="file" name="f[medias][0][file]">')
                . $this->field('nested', '<div class="block-data-form"><input name="f[data][0][x]"></div>', true)
                . $this->field('other', '<input name="unrelated[0][x]">')
            ),
            ['blockDuplicate' => 'block-duplicate'],
            'return ["block", "media", "nested", "other"].map((id) => !!root.querySelector("#" + id + " .field-collection-item .ui-toolbar-btn"));'
        );

        $this->assertSame([true, true, true, false], $owned, 'The rows this controller acts on are no longer told apart from the ones it must leave alone - either a block row lost its duplicate button, or an unrelated collection elsewhere in the admin grew one.');
    }

    // A block's own media collection also has an "add" button, and it sits earlier in the DOM than the outer one: a plain querySelector grabs that one and every duplication appends to the wrong collection
    public function testTheAddButtonUsedIsTheCollectionsOwnAndNeverANestedOne(): void
    {
        $clicked = $this->observe(
            $this->mounted('<div id="outer" data-ea-collection-field data-prototype="">
                <div class="field-collection-item"><div class="accordion-header"></div>
                    <div data-kind-row><select><option value="text" selected>Text</option></select></div>
                    <div id="inner" data-ea-collection-field data-prototype="&lt;input type=&quot;file&quot;&gt;">
                        <button type="button" class="field-collection-add-button" data-which="inner"></button>
                    </div>
                </div>
                <button type="button" class="field-collection-add-button" data-which="outer"></button>
            </div>'),
            ['blockDuplicate' => 'block-duplicate'],
            'let which = null;
             for (const button of root.querySelectorAll(".field-collection-add-button")) {
                 button.addEventListener("click", () => { which = which ?? button.dataset.which; });
             }
             root.querySelector(".ui-toolbar-btn").click();
             await new Promise((r) => setTimeout(r, 50));

             return which;'
        );

        $this->assertSame('outer', $clicked, 'Duplicating a block clicked the "add" button of a collection nested inside it, so the copy is appended to the wrong collection entirely.');
    }

    // Duplicating a media row is a straight DOM-to-DOM copy, and it has to land right under its source rather than at the end of the collection
    public function testADuplicatedMediaLandsUnderItsSourceCarryingItsValues(): void
    {
        $state = $this->observe(
            $this->mounted($this->mediaField()),
            ['blockDuplicate' => 'block-duplicate'],
            'root.querySelector("#one .ui-toolbar-btn").click();
             await new Promise((r) => setTimeout(r, 50));
             const rows = [...root.querySelectorAll(".field-collection-item")];

             return {
                 order: rows.map((r) => r.id || "copy"),
                 alt: rows[1].querySelector("[name$=\'[alt]\']").value,
                 rounded: rows[1].querySelector("[value=rounded]").checked,
                 thumb: rows[1].querySelector("[value=thumbnail]").checked,
                 positions: rows.map((r) => r.querySelector("[name$=\'[position]\']").value),
             };'
        );

        $this->assertSame(['one', 'copy', 'two'], $state['order'], 'The copy was appended at the end of the collection instead of right under the row it was made from.');
        $this->assertSame('Une image', $state['alt'], 'The copy carries none of its source\'s values.');
        $this->assertSame(['0', '1', '2'], $state['positions'], 'The positions were not renumbered, so the order saved is not the order on screen.');
    }

    // A multi-checkbox field renders several inputs sharing one name, one per value: keyed by name alone they all collapse onto whichever was seen last, and only that one is ever copied
    public function testEachCheckboxOfAMultipleChoiceFieldIsCopiedOnItsOwnValue(): void
    {
        $state = $this->observe(
            $this->mounted($this->mediaField()),
            ['blockDuplicate' => 'block-duplicate'],
            'root.querySelector("#one .ui-toolbar-btn").click();
             await new Promise((r) => setTimeout(r, 50));
             const copy = [...root.querySelectorAll(".field-collection-item")][1];

             return { rounded: copy.querySelector("[value=rounded]").checked, thumb: copy.querySelector("[value=thumbnail]").checked };'
        );

        $this->assertTrue($state['rounded'], 'A ticked box of a multiple-choice field was not copied.');
        $this->assertFalse($state['thumb'], 'An unticked box of a multiple-choice field came back ticked, so every box collapsed onto one another.');
    }

    // A source row with an existing file carries a Vich "delete" checkbox a fresh row has not got, so the two rows cannot be lined up by position
    public function testFieldsAreMatchedByNameSoAnExtraFieldOnTheSourceShiftsNothing(): void
    {
        $copied = $this->observe(
            $this->mounted(str_replace('<!--extra-->', '<input type="checkbox" name="f[medias][0][file][delete]" checked>', $this->mediaField())),
            ['blockDuplicate' => 'block-duplicate'],
            'root.querySelector("#one .ui-toolbar-btn").click();
             await new Promise((r) => setTimeout(r, 50));
             const copy = [...root.querySelectorAll(".field-collection-item")][1];

             return { alt: copy.querySelector("[name$=\'[alt]\']").value, title: copy.querySelector("[name$=\'[title]\']").value };'
        );

        $this->assertSame('Une image', $copied['alt'], 'An extra field on the source shifted every value by one, so the copy holds another field\'s content.');
        $this->assertSame('Un titre', $copied['title']);
    }

    // EasyAdmin shows an existing image behind a lightbox link whose href is only "#", so the real address is on the <img> and nowhere else
    public function testTheExistingFileIsFoundOnTheThumbnailRatherThanOnItsLightboxLink(): void
    {
        $requested = $this->observe(
            $this->mounted($this->mediaField('<a href="#"><img src="/UiBundle/public/css/images/marker-icon.png"></a>')),
            ['blockDuplicate' => 'block-duplicate'],
            'const asked = [];
             const original = window.fetch;
             window.fetch = (url, ...rest) => { asked.push(String(url)); return original(url, ...rest); };
             try {
                 root.querySelector("#one .ui-toolbar-btn").click();
                 await new Promise((r) => setTimeout(r, 200));
             } finally {
                 window.fetch = original;
             }

             return asked;'
        );

        $this->assertNotSame([], $requested, 'The existing file was never fetched, so the copy is saved with no file at all.');
        $this->assertStringContainsString('marker-icon.png', (string) $requested[0], 'The lightbox link\'s own "#" was taken for the file address.');
    }

    // Rows of a collection nested inside an item are not that collection's own rows, and renumbering as though they were writes positions into somebody else's field
    public function testANestedCollectionsRowsAreNeverCountedAsTheOuterOnes(): void
    {
        $positions = $this->observe(
            $this->mounted('<div data-ea-collection-field data-prototype="">
                <div class="field-collection-item" id="one"><div class="accordion-header"></div>
                    <input type="file" name="f[medias][0][file]"><input name="f[medias][0][position]" value="9">
                    <div data-ea-collection-field data-prototype="">
                        <div class="field-collection-item"><input name="f[medias][0][sub][0][position]" value="7"></div>
                    </div>
                </div>
                <button type="button" class="field-collection-add-button"></button>
            </div>'),
            ['blockDuplicate' => 'block-duplicate'],
            'const field = root.querySelector("[data-ea-collection-field]");
             field.querySelector(".field-collection-add-button").addEventListener("click", () => {
                 const row = document.createElement("div");
                 row.className = "field-collection-item";
                 row.innerHTML = "<div class=\'accordion-header\'></div><input type=\'file\' name=\'f[medias][1][file]\'><input name=\'f[medias][1][position]\' value=\'\'>";
                 field.insertBefore(row, field.querySelector(".field-collection-add-button"));
             });
             root.querySelector("#one .ui-toolbar-btn").click();
             await new Promise((r) => setTimeout(r, 50));

             return { outer: [...field.querySelectorAll(":scope > .field-collection-item [name$=\'[position]\']")].length, nested: root.querySelector("[name=\'f[medias][0][sub][0][position]\']").value };'
        );

        $this->assertSame('7', $positions['nested'], 'A row of a nested collection was renumbered as though it belonged to the outer one.');
    }

    // The controller is mounted on <body> by controllers-admin.js, so here it wraps the whole fixture: what it does depends entirely on what it finds under it
    private function mounted(string $html): string
    {
        return '<div data-controller="blockDuplicate">' . $html . '</div>';
    }

    // A collection field with rows, an accordion header on each so the toolbar has somewhere to go, and an add button that appends the way EasyAdmin's does
    private function field(string $id, string $body, bool $inDataForm = false): string
    {
        return sprintf(
            '<div id="%s" %sdata-ea-collection-field data-prototype="">
                <div class="field-collection-item"><div class="accordion-header"></div>%s</div>
                <button type="button" class="field-collection-add-button"></button>
            </div>',
            $id,
            $inDataForm ? 'class="block-data-form" ' : '',
            $body
        );
    }

    // Two media rows, the first carrying the values a copy has to come back with
    private function mediaField(string $thumbnail = ''): string
    {
        return sprintf(
            '<div id="medias" data-ea-collection-field data-prototype="&lt;input type=&quot;file&quot;&gt;">
                <div class="field-collection-item" id="one"><div class="accordion-header"></div>
                    %s
                    <input type="file" name="f[medias][0][file]">
                    <!--extra-->
                    <input name="f[medias][0][alt]" value="Une image">
                    <input name="f[medias][0][title]" value="Un titre">
                    <input type="checkbox" name="f[medias][0][cssClasses][]" value="rounded" checked>
                    <input type="checkbox" name="f[medias][0][cssClasses][]" value="thumbnail">
                    <input name="f[medias][0][position]" value="0">
                </div>
                <div class="field-collection-item" id="two"><div class="accordion-header"></div>
                    <input type="file" name="f[medias][1][file]">
                    <input name="f[medias][1][alt]" value="">
                    <input name="f[medias][1][title]" value="">
                    <input type="checkbox" name="f[medias][1][cssClasses][]" value="rounded">
                    <input type="checkbox" name="f[medias][1][cssClasses][]" value="thumbnail">
                    <input name="f[medias][1][position]" value="1">
                </div>
                <button type="button" class="field-collection-add-button" onclick="(function(b){
                    const field = b.parentElement;
                    const row = document.createElement(\'div\');
                    row.className = \'field-collection-item\';
                    row.innerHTML = \'<div class=&quot;accordion-header&quot;></div><input type=&quot;file&quot; name=&quot;f[medias][9][file]&quot;><input name=&quot;f[medias][9][alt]&quot; value=&quot;&quot;><input name=&quot;f[medias][9][title]&quot; value=&quot;&quot;><input type=&quot;checkbox&quot; name=&quot;f[medias][9][cssClasses][]&quot; value=&quot;rounded&quot;><input type=&quot;checkbox&quot; name=&quot;f[medias][9][cssClasses][]&quot; value=&quot;thumbnail&quot;><input name=&quot;f[medias][9][position]&quot; value=&quot;&quot;>\';
                    field.insertBefore(row, b);
                })(this)"></button>
            </div>',
            $thumbnail
        );
    }
}
