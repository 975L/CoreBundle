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

// assets/js/upload-progress.js driven through a request that reports its own progress, standing in for a browser uploading a batch of photos
// The whole controller is a state machine over one request, and every state it can get wrong is a page that looks like it has stopped: a bar frozen at 100 while the server works, a submit handed back too early and the batch sent twice, a failure leaving the button disabled with nothing to click
#[Group('browser')]
class UploadProgressBehaviourTest extends JsCase
{
    // A request whose progress and outcome the scenario drives itself
    private const string XHR = 'window.__sent = [];
        window.XMLHttpRequest = class {
            constructor() { this.upload = new EventTarget(); this.status = 200; this.responseText = ""; this.headers = {}; window.__xhr = this; }
            open(method, url) { this.method = method; this.url = url; }
            setRequestHeader(name, value) { this.headers[name] = value; }
            getResponseHeader(name) { return this.responseHeaders?.[name] ?? null; }
            addEventListener(type, listener) { (this.listeners ??= {})[type] = listener; }
            send(data) { window.__sent.push(data); }
            transfer(loaded, total, lengthComputable = true) { this.upload.dispatchEvent(Object.assign(new Event("progress"), { loaded, total, lengthComputable })); }
            finish() { this.upload.dispatchEvent(new Event("load")); }
            answer() { this.listeners.load(); }
            break() { this.listeners.error(); }
        };';

    // The form goes out from here on: a native submit alongside would send the whole batch twice
    public function testTheFormIsSentFromHereAndTheSubmitTakenAwayForTheWait(): void
    {
        $state = $this->upload(
            'let native = 0;
             root.querySelector("form").addEventListener("submit", () => { native += 1; });
             root.querySelector("[type=submit]").click();
             await tick();

             return { native, sent: window.__sent.length, disabled: root.querySelector("[type=submit]").disabled, header: window.__xhr.headers["X-Requested-With"] };'
        );

        $this->assertSame(1, $state['native'], 'The submit event never reached the controller.');
        $this->assertSame(1, $state['sent'], 'The form was not sent over a request of its own, so nothing can report its progress.');
        $this->assertTrue($state['disabled'], 'The submit is still there to be clicked again, which sends the whole batch a second time.');
        $this->assertSame('XMLHttpRequest', $state['header'], 'The request does not announce itself, so the server answers with a redirect this request follows on a page nobody sees.');
    }

    // The transfer has a percentage, and it is stated in what the visitor is actually waiting on
    public function testTheTransferIsReportedAsAPercentageAndASize(): void
    {
        $state = $this->upload(
            'root.querySelector("[type=submit]").click();
             await tick();
             window.__xhr.transfer(1024 * 1024 * 56.2, 1024 * 1024 * 112.4);
             await tick();

             return { value: root.querySelector("progress").value, said: root.querySelector(".upload-progress-status").textContent };'
        );

        $this->assertSame(50, $state['value'], 'The bar does not follow the transfer.');
        $this->assertSame('50% - 56.2 of 112.4 MB', $state['said'], 'The wait is not stated in the size being sent, so a batch of photos reads as a page doing nothing.');
    }

    // A transfer whose total the browser cannot state leaves the bar where it stands rather than jumping to a made-up figure
    public function testATransferWithNoKnownTotalLeavesTheBarWhereItStands(): void
    {
        $this->assertSame(
            30,
            $this->upload(
                'root.querySelector("[type=submit]").click();
                 await tick();
                 window.__xhr.transfer(30, 100);
                 window.__xhr.transfer(90, 0, false);
                 await tick();

                 return root.querySelector("progress").value;'
            ),
            'A transfer of unknown size moved the bar to a figure nothing measured.'
        );
    }

    // The second phase has no percentage at all, and a bar frozen at 100 reads as a page that has stopped
    public function testOnceTheLastByteHasLeftTheBarGoesIndeterminate(): void
    {
        $state = $this->upload(
            'root.querySelector("[type=submit]").click();
             await tick();
             window.__xhr.transfer(100, 100);
             window.__xhr.finish();
             await tick();

             return { value: root.querySelector("progress").getAttribute("value"), said: root.querySelector(".upload-progress-status").textContent };'
        );

        $this->assertNull($state['value'], 'The bar stays at a figure while the server works, which reads as a page that has stopped.');
        $this->assertSame('Traitement en cours', $state['said'], 'The second phase is not announced, so the wait looks like the first one stalled.');
    }

    // A batch the network refused is one to send again, and the selection made is still on the screen
    public function testAFailedTransferHandsTheSubmitBackAndSaysWhy(): void
    {
        $state = $this->upload(
            'root.querySelector("[type=submit]").click();
             await tick();
             window.__xhr.break();
             await tick();

             return { disabled: root.querySelector("[type=submit]").disabled, said: root.querySelector(".upload-progress-status").textContent, bar: root.querySelector("progress").hidden };'
        );

        $this->assertFalse($state['disabled'], 'A failed transfer left the submit disabled, so the visitor is stuck on a form that cannot be sent.');
        $this->assertSame('Envoi impossible', $state['said'], 'A failure says nothing at all.');
        $this->assertTrue($state['bar'], 'The bar is still there after a failure, showing a progress that stopped.');
    }

    // A second attempt takes the panel already in the form back rather than piling a fresh bar under the message of the one before
    public function testASecondAttemptReusesTheSamePanel(): void
    {
        $panels = $this->upload(
            'const submit = root.querySelector("[type=submit]");
             submit.click();
             await tick();
             window.__xhr.break();
             await tick();
             submit.click();
             await tick();

             return { panels: root.querySelectorAll(".upload-progress").length, bar: root.querySelector("progress").hidden };'
        );

        $this->assertSame(1, $panels['panels'], 'Each attempt built its own panel, so the form fills with bars.');
        $this->assertFalse($panels['bar'], 'The bar hidden by the previous failure was not shown again for the new attempt.');
    }

    // The clicked button goes in as an entry of its own, which is how EasyAdmin knows what screen to land on once saved - and it is read before the wait disables it, a disabled control being left out of what a form sends
    public function testTheClickedButtonIsPartOfWhatIsSent(): void
    {
        $this->assertTrue(
            (bool) $this->upload(
                'root.querySelector("[name=\'ea[newForm][btn]\']").click();
                 await tick();

                 return [...window.__sent[0].keys()].includes("ea[newForm][btn]");'
            ),
            'The button that was clicked is not part of what the form sends, so the server cannot tell which screen to land on.'
        );
    }

    // The status is announced and not merely displayed: from the submit on, the wait is what the screen is about
    public function testTheWaitIsAnnouncedToAScreenReader(): void
    {
        $this->assertSame(
            'status',
            $this->upload('root.querySelector("[type=submit]").click(); await tick(); return root.querySelector(".upload-progress-status").getAttribute("role");'),
            'The wait is shown but never announced, so a screen reader is told nothing between the click and the answer.'
        );
    }

    private function upload(string $probe): mixed
    {
        return $this->observe(
            '<div data-controller="uploadProgress"
                  data-action="submit->uploadProgress#send"
                  data-uploadprogress-uploading-message-value="%percent%% - %sent% of %total% MB"
                  data-uploadprogress-processing-message-value="Traitement en cours"
                  data-uploadprogress-failed-message-value="Envoi impossible">
                <form action="/save" id="batch">
                    <input type="file" name="files[]" multiple>
                    <button type="submit" name="ea[newForm][btn]" value="save">Save</button>
                </form>
            </div>',
            ['uploadProgress' => 'upload-progress'],
            'const tick = () => new Promise((r) => setTimeout(r, 30)); ' . $probe,
            ['before' => self::XHR]
        );
    }
}
