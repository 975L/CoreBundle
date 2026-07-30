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

// window.getSelection() answers for the whole document, so a page holding several editors hands every toolbar the same node: without the scope check, pressing "align right" in one moved the block of whichever editor the caret was actually in. Locked statically, the repository having no browser to run the controller in
class TrixEditorSelectionScopeTest extends TestCase
{
    public function testTheBlockLookupIsScopedToItsOwnEditor(): void
    {
        $script = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/trix-editor.js');

        $this->assertMatchesRegularExpression(
            '/function currentBlockElement\(editorElement\)\s*\{.*?editorElement\.contains\(element\).*?\}/s',
            $script,
            'currentBlockElement() no longer checks the selected node belongs to the editor it was called for, so a toolbar can align a block of another editor on the page.'
        );
    }
}
