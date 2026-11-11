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

// assets/js/age-gate.js with go.cam never reached: its frame loads a blank page, and its messages are dispatched by the probe with go.cam's origin
#[Group('browser')]
class AgeGateBehaviourTest extends JsCase
{
    // fetch replaced, so the result posted is read back and the server's refusal is what answers
    private const string SEAM = 'window.__posted = [];
        window.fetch = (url, init) => { window.__posted.push({ url, body: init.body }); return Promise.resolve(new Response("{}", { status: 400 })); };';

    // What go.cam sends, from its frame and its origin unless the scenario says otherwise
    private const string SEND = 'const frame = document.querySelector(".age-gate__frame");
        const send = (name, data = {}, origin = "https://go.cam") => window.dispatchEvent(new MessageEvent("message", { origin, source: frame.contentWindow, data: { name, data } }));';

    // The button waits for go.cam's models, then opens the frame over the page
    public function testTheButtonOpensTheFrameOnceGoCamIsReady(): void
    {
        $state = $this->gate(self::SEND . '
            const button = root.querySelector("button");
            const before = button.disabled;
            send("avs.onResourcesLoaded");
            button.click();

            return { before, after: button.disabled, open: frame.classList.contains("age-gate__frame--open") };');

        $this->assertTrue($state['before'], 'The button can be clicked before go.cam is ready, opening an empty frame.');
        $this->assertFalse($state['after'], 'go.cam said it was ready and the button stayed disabled.');
        $this->assertTrue($state['open'], 'The click did not lay the frame over the page.');
    }

    // Any page can post a message to this window: only go.cam's are read
    public function testAMessageFromAnotherOriginIsIgnored(): void
    {
        $state = $this->gate(self::SEND . '
            send("avs.onResourcesLoaded", {}, "https://elsewhere.example");
            send("avs.onVerificationSuccess", { status: true, payload: "forged" }, "https://elsewhere.example");
            await new Promise((r) => setTimeout(r, 60));

            return { disabled: root.querySelector("button").disabled, posted: window.__posted.length };');

        $this->assertTrue($state['disabled'], 'A message from another origin enabled the button.');
        $this->assertSame(0, $state['posted'], 'A message from another origin was posted to the server as a result.');
    }

    // The result goes to the server, which alone decides; refused, the visitor is told so
    public function testTheResultIsPostedAndARefusalIsShown(): void
    {
        $state = $this->gate(self::SEND . '
            send("avs.onVerificationSuccess", { status: true, payload: "sealed-result", isPayloadValid: true });
            await new Promise((r) => setTimeout(r, 60));

            return { posted: window.__posted, error: root.querySelector(".age-gate__error").hidden };');

        $this->assertSame('/age-verification', $state['posted'][0]['url']);
        $this->assertSame('{"payload":"sealed-result"}', $state['posted'][0]['body']);
        $this->assertFalse($state['error'], 'The server refused the result and the visitor was told nothing.');
    }

    // Three gates on a list load go.cam once, and every one of them opens that frame and says the result was refused
    public function testSeveralGatesShareOneFrame(): void
    {
        $state = $this->gate(self::SEND . '
            const frames = document.querySelectorAll(".age-gate__frame").length;
            send("avs.onResourcesLoaded");
            const buttons = [...root.querySelectorAll("button")];
            const enabled = buttons.every((button) => !button.disabled);
            buttons[2].click();
            const open = frame.classList.contains("age-gate__frame--open");
            send("avs.onVerificationSuccess", { payload: "sealed-result" });
            await new Promise((r) => setTimeout(r, 60));

            return { frames, enabled, open, posted: window.__posted.length, errors: [...root.querySelectorAll(".age-gate__error")].filter((error) => !error.hidden).length };', 3);

        $this->assertSame(1, $state['frames'], 'Each gate loaded its own go.cam frame.');
        $this->assertTrue($state['enabled'], 'go.cam said it was ready and a gate kept its button disabled.');
        $this->assertTrue($state['open'], 'The third gate\'s button did not open the shared frame.');
        $this->assertSame(1, $state['posted'], 'One result was posted more than once.');
        $this->assertSame(3, $state['errors'], 'A refused result left a gate silent.');
    }

    private function gate(string $probe, int $count = 1): mixed
    {
        return $this->observe(
            str_repeat('<div class="age-gate" data-controller="age-gate" data-age-gate-url-value="about:blank" data-age-gate-confirm-value="/age-verification">
                <p class="age-gate__text">text</p>
                <button type="button" data-age-gate-target="button" data-action="age-gate#open" disabled>Verify</button>
                <p class="age-gate__error" data-age-gate-target="error" hidden>failed</p>
            </div>', $count),
            ['age-gate' => 'age-gate'],
            $probe,
            ['before' => self::SEAM . 'document.querySelectorAll(".age-gate__frame").forEach((frame) => frame.remove());', 'settle' => 60]
        );
    }
}
