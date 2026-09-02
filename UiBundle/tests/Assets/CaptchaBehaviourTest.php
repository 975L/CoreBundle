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

// assets/js/captcha.js with Google's api.js standing in for itself: a stub served in its place, and the request left to fail where the failure is the point
// Two promises this controller makes are only ever kept at runtime - that a visitor who never touches the form downloads none of the ~765 KB, and that a form whose captcha is blocked still submits. The second is the one that matters: a page that silently refuses to submit is a contact form nobody can reach the site through
#[Group('browser')]
class CaptchaBehaviourTest extends JsCase
{
    // Google's script replaced on its way into the page, so the happy path can be walked without leaving the machine. Installed once for the life of the page and switched per scenario, because a wrapper laid over the previous one would count every append twice
    private const string SEAM = 'window.__asked = [];
        if (!window.__seam) {
            window.__seam = true;
            const appendChild = Node.prototype.appendChild;
            Node.prototype.appendChild = function (element) {
                if ("SCRIPT" === element.tagName && String(element.src).includes("recaptcha")) {
                    window.__asked.push(element.src);
                    if (window.__stub) {
                        element.src = "data:text/javascript,window.grecaptcha={ready:(f)=>f(),execute:()=>Promise.resolve(\'a-token\')};";
                    }
                }

                return appendChild.call(this, element);
            };
        }';

    // The script is the whole cost of this feature, and a visitor reading a page with a form on it must not pay it
    public function testNothingIsFetchedUntilTheVisitorTouchesTheForm(): void
    {
        $asked = $this->captcha('return window.__asked.length;');

        $this->assertSame(0, $asked, 'Google\'s script is fetched on page load, which is the ~765 KB this controller exists to defer.');
    }

    // Once, on the first interaction, and never again however many follow
    public function testTheFirstInteractionFetchesTheScriptExactlyOnce(): void
    {
        $asked = $this->captcha(
            'const form = root.querySelector("form");
             form.dispatchEvent(new FocusEvent("focusin", { bubbles: true }));
             form.dispatchEvent(new FocusEvent("focusin", { bubbles: true }));
             form.dispatchEvent(new PointerEvent("pointerdown", { bubbles: true }));
             await new Promise((r) => setTimeout(r, 60));

             return window.__asked.length;'
        );

        $this->assertSame(1, $asked, 'The script is fetched a number of times other than once, so either the form costs nothing or it costs it repeatedly.');
    }

    // The token is asked for on submit and not before, which is also what keeps it fresh: one grabbed on page load expires in two minutes
    public function testTheTokenIsFetchedOnSubmitAndWrittenIntoTheField(): void
    {
        $state = $this->captcha(
            'const form = root.querySelector("form");
             let submits = 0;
             form.addEventListener("submit", (event) => { submits += 1; event.preventDefault(); });
             form.requestSubmit();
             await new Promise((r) => setTimeout(r, 300));

             return { token: root.querySelector("[name=captcha]").value, submits, asked: window.__asked.length };'
        );

        $this->assertSame('a-token', $state['token'], 'No token reached the field, so every submission is unverifiable.');
        $this->assertSame(2, $state['submits'], 'The form was not submitted again once the token was in place, so the visitor\'s submission is simply dropped.');
        $this->assertSame(1, $state['asked'], 'The script was fetched more than once for a single submission.');
    }

    // Blocked, offline or refused: deciding what an unverifiable submission is worth belongs to the server, never to a page that will not submit
    public function testAFormWhoseCaptchaCannotLoadIsSubmittedAllTheSame(): void
    {
        $state = $this->captcha(
            'const form = root.querySelector("form");
             let submits = 0;
             form.addEventListener("submit", (event) => { submits += 1; event.preventDefault(); });
             form.requestSubmit();
             await new Promise((r) => setTimeout(r, 900));

             return { token: root.querySelector("[name=captcha]").value, submits };',
            // No stub this time: the real address is left to fail, which is what a blocker or a lost network does
            ''
        );

        $this->assertSame('', $state['token'], 'A token appeared although nothing could be loaded to mint one.');
        $this->assertSame(2, $state['submits'], 'A form whose captcha could not load was never submitted, leaving the visitor on a page that refuses to send.');
    }

    // Another listener already refused this submission - a confirm dialog, a consumer's own validation - and calling requestSubmit would overrule it
    public function testASubmissionAlreadyCancelledIsLeftCancelled(): void
    {
        $state = $this->captcha(
            'const form = root.querySelector("form");
             let submits = 0;
             // The form carries an inline handler that refuses the submission before this one ever counts it
             form.addEventListener("submit", () => { submits += 1; });
             form.requestSubmit();
             await new Promise((r) => setTimeout(r, 300));

             return { submits, asked: window.__asked.length };',
            true,
            true
        );

        $this->assertSame(1, $state['submits'], 'A submission another listener had already cancelled was submitted again anyway.');
        $this->assertSame(0, $state['asked'], 'A token was fetched for a submission that had already been refused.');
    }

    private function captcha(string $probe, bool $stub = true, bool $cancelFirst = false): mixed
    {
        // An inline handler is registered as the form is parsed, so it runs before the controller ever connects - which is the only way to put a refusal in front of it
        $guard = $cancelFirst ? ' onsubmit="event.preventDefault();"' : '';

        return $this->observe(
            sprintf(
                '<form%s><input type="hidden" name="captcha" data-controller="captcha" data-captcha-site-key-value="key" data-captcha-action-value="contact"><input name="x"><button type="submit">Send</button></form>',
                $guard
            ),
            ['captcha' => 'captcha'],
            $probe,
            [
                // Whatever the previous scenario left behind: its recorded appends, its stub, and the grecaptcha a loaded stub had defined
                'before' => self::SEAM . sprintf('window.__stub = %s; delete window.grecaptcha;', $stub ? 'true' : 'false'),
                'settle' => 60,
            ]
        );
    }
}
