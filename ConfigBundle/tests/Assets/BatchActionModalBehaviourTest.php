<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/batch-action-modal.js run over EasyAdmin's batch modal: its "cannot be undone" line has to say the truth about the action that opened it
#[Group('browser')]
class BatchActionModalBehaviourTest extends JsCase
{
    // The batch bar and the modal as EasyAdmin renders them, the button reading its variant from the action
    private const string PAGE = '<button type="button" id="trash" data-action-batch data-action-variant="warning">Move to the recycle bin</button>
        <button type="button" id="delete" data-action-batch data-action-variant="danger">Delete permanently</button>
        <button type="button" id="plain" data-action-batch>Delete</button>
        <div id="modal-batch-action">
            <h4 id="batch-action-confirmation-title">Apply</h4>
            <p id="batch-action-confirmation-message" class="modal-message d-none"></p>
            <p id="content">This action cannot be undone.</p>
        </div>';

    // The recycle bin can be emptied back, so the line goes; the next action opening the same modal brings it back when it is a danger one
    public function testTheLineOnlyStaysForADangerAction(): void
    {
        $shown = $this->modal(
            'const hidden = () => root.querySelector("#content").classList.contains("d-none");
             root.querySelector("#trash").click();
             const trash = hidden();
             root.querySelector("#delete").click();
             const del = hidden();
             root.querySelector("#trash").click();
             root.querySelector("#plain").click();

             return { trash, del, plain: hidden() };'
        );

        $this->assertTrue($shown['trash'], 'Moving to the recycle bin is still announced as something that cannot be undone.');
        $this->assertFalse($shown['del'], 'A permanent deletion lost its "cannot be undone" warning.');
        $this->assertFalse($shown['plain'], 'An action carrying no variant is danger to EasyAdmin, yet lost its warning.');
    }

    private function modal(string $probe): mixed
    {
        return $this->observe(self::PAGE, [], $probe, ['modules' => ['modal' => 'batch-action-modal']]);
    }
}
