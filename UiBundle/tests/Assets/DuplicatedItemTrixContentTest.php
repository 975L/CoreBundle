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

// EasyAdmin's "add" click fires ea.collection.item-added synchronously, so trix-editor.js has already built an editor on the (still empty) textarea of the new row by the time the source row's values are copied into it - and Trix reads that textarea only once, when the editor is built. Duplicating a card, a step or a slide therefore has to drop that editor and have it rebuilt from the copied value, else the rich text silently stays empty. Locked statically, the repository having no browser to run the controller in
class DuplicatedItemTrixContentTest extends TestCase
{
    private function script(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/block-duplicate.js');
    }

    public function testDuplicatingAnItemResetsItsTrixEditorsAfterCopyingTheValues(): void
    {
        $this->assertMatchesRegularExpression(
            '/copyItemValues\(sourceItem, newItem\);.*?resetTrixEditors\(newItem\);.*?c975l:block-data-loaded/s',
            $this->script(),
            "duplicateItem() no longer drops the new row's empty Trix editor after copying the values, so the duplicated rich text never reaches the editor."
        );
    }

    public function testResettingATrixEditorClearsTheMarkerSoTheSetupRunsAgain(): void
    {
        $this->assertMatchesRegularExpression(
            '/resetTrixEditors\(item\)\s*\{.*?delete textarea\.dataset\.trixInit;.*?\}/s',
            $this->script(),
            'resetTrixEditors() no longer clears the data-trix-init marker, so trix-editor.js skips the textarea and the duplicated row keeps a bare textarea.'
        );
    }

    // A Trix toolbar ships its own named input (the link dialog's "href"): counted as a form field, it shifts every field after it as soon as one of the two rows has an editor and the other doesn't
    public function testTheValueCopySkipsTrixToolbarInputs(): void
    {
        $this->assertStringContainsString(
            "!el.closest('trix-toolbar')",
            $this->script(),
            "copyItemValues() no longer skips Trix's own toolbar inputs, so the positional field matching drifts on rows holding a rich-text field."
        );
    }
}
