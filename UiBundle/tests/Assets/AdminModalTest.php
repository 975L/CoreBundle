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

// A back-office message is shown in a Bootstrap modal, not in a native alert() that freezes the tab and looks like nothing else on the screen. None of this is provable from a unit test - it is a browser dialog - so what is locked is that the pieces making it a modal stay in place.
class AdminModalTest extends TestCase
{
    public function testTheMessageIsShownInABootstrapModal(): void
    {
        $js = $this->js('admin-modal.js');

        $this->assertStringContainsString('export function showAdminMessage', $js, 'The helper is no longer exported, so no script can reuse it.');
        $this->assertStringContainsString('window.bootstrap.Modal.getOrCreateInstance', $js, 'Nothing opens a Bootstrap modal any more.');
    }

    // EasyAdmin's shared confirmation modal keeps whatever handler the last opened action attached to its button, so dismissing a message could run a delete - this one builds its own element instead of querying for that one
    public function testItBuildsItsOwnModalElement(): void
    {
        $js = $this->js('admin-modal.js');

        $this->assertStringContainsString("document.createElement('div')", $js, 'The modal is no longer built as an element of its own.');
        $this->assertStringNotContainsString("querySelector('#modal-action-confirmation')", $js, 'EasyAdmin\'s shared confirmation modal is borrowed again, and its button may still carry another action.');
    }

    // Left in the DOM, every failed move would stack one more hidden modal on the page
    public function testTheModalIsRemovedOnceDismissed(): void
    {
        $js = $this->js('admin-modal.js');

        $this->assertStringContainsString('hidden.bs.modal', $js, 'Nothing reacts to the modal being closed.');
        $this->assertStringContainsString('modal.remove()', $js, 'The modal is never taken back out of the DOM.');
    }

    // A failed block move is the reason this exists: a bare "it failed" leaves the editor with nothing to act on
    public function testAFailedBlockMoveGoesThroughItAndCarriesTheServersReason(): void
    {
        $js = $this->js('ea-sortable.js');

        $this->assertStringContainsString('showAdminMessage', $js, 'The sortable no longer reports its failures through the modal.');
        $this->assertStringNotContainsString('window.alert', $js, 'A native alert is back in the sortable.');
        $this->assertStringContainsString('uiMoveCloseLabel', $js, 'The modal\'s dismiss button is no longer labelled from the translated attribute (see BlockMoveRowAttrBuilder, which now speaks the generic move vocabulary).');
        $this->assertStringContainsString('refusalReason', $js, 'The server\'s own explanation is no longer read back from the response.');
    }

    private function js(string $file): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/assets/js/' . $file);
    }
}
