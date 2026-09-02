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

// assets/js/password.js typed into and blurred, with the submit button read back after each field
// The rule this controller exists for is the one across fields: a confirmation that matches must not re-open a submit an invalid password closed. Nothing but running it can tell whether it holds
#[Group('browser')]
class PasswordBehaviourTest extends JsCase
{
    // The eye is built around the input alone, and built once however often the controller reconnects
    public function testTheToggleIsBuiltAroundTheInputAndNeverStacked(): void
    {
        $built = $this->form(
            'const form = root.querySelector("form");
             // Turbo takes a page away and puts it back, which disconnects and reconnects the controller on the very same fields
             form.remove();
             await new Promise((r) => setTimeout(r, 20));
             root.appendChild(form);
             await new Promise((r) => setTimeout(r, 20));
             const input = root.querySelector("#plain");

             return {
                 wrapped: input.parentElement.classList.contains("has-toggle"),
                 toggles: input.parentElement.querySelectorAll(".toggle-password").length,
                 fields: root.querySelectorAll(".has-toggle").length,
                 labelOutside: !root.querySelector("label").closest(".has-toggle"),
             };'
        );

        $this->assertTrue($built['wrapped'], 'The password input is not wrapped, so the eye has nothing to sit in.');
        $this->assertSame(1, $built['toggles'], 'A reconnect stacked a second eye on the same field.');
        $this->assertSame(2, $built['fields'], 'Not every password field of the form got its own eye.');
        $this->assertTrue($built['labelOutside'], 'The label was wrapped along with the input, which pushes the eye off the input it belongs to.');
    }

    // Revealing a password must not let a password manager treat the now-visible field as a plain text one to fill, and hiding it again has to put back exactly what the form declared
    public function testRevealingAPasswordSuspendsItsAutocompleteAndHidingRestoresIt(): void
    {
        $states = $this->form(
            'const input = root.querySelector("#plain");
             const toggle = root.querySelector(".toggle-password");
             const seen = [];
             toggle.click();
             seen.push({ type: input.type, autocomplete: input.getAttribute("autocomplete"), icon: root.querySelector(".toggle-password img").getAttribute("src") });
             toggle.click();
             seen.push({ type: input.type, autocomplete: input.getAttribute("autocomplete"), icon: root.querySelector(".toggle-password img").getAttribute("src") });

             return seen;'
        );

        $this->assertSame('text', $states[0]['type'], 'The eye does not reveal the password.');
        $this->assertSame('off', $states[0]['autocomplete'], 'A revealed password keeps its autocomplete, so a manager may fill a field the visitor can read.');
        $this->assertStringContainsString('eye-slash', (string) $states[0]['icon'], 'The eye does not change once the password is revealed.');
        $this->assertSame('password', $states[1]['type']);
        $this->assertSame('new-password', $states[1]['autocomplete'], 'Hiding the password again did not put back what the form had declared.');
    }

    // A form declaring no autocomplete on a field must get none back, and never a value the controller picked for it
    public function testAFieldTheFormDeclaredNoAutocompleteOnGetsNoneBack(): void
    {
        $states = $this->form(
            'const input = root.querySelector("#confirm");
             const toggle = input.parentElement.querySelector(".toggle-password");
             toggle.click();
             const revealed = input.getAttribute("autocomplete");
             toggle.click();

             return { revealed, hidden: input.getAttribute("autocomplete") };'
        );

        $this->assertSame('off', $states['revealed'], 'A revealed password keeps whatever autocomplete it had, so a manager may fill a field the visitor can read.');
        $this->assertNull($states['hidden'], 'Hiding the password put an autocomplete on a field the form had declared none for.');
    }

    // The message is the form's own when it names one, and a translated default otherwise
    public function testAPasswordFailingItsPatternIsToldWhyAndHoldsTheSubmitBack(): void
    {
        $state = $this->form(
            'const input = root.querySelector("#plain");
             input.value = "short";
             input.dispatchEvent(new FocusEvent("blur"));
             await new Promise((r) => setTimeout(r, 30));

             return {
                 error: input.classList.contains("error"),
                 message: root.querySelector(".error-message")?.textContent ?? null,
                 describedBy: root.querySelector(".error-message")?.id ?? null,
                 submit: root.querySelector("[type=submit]").disabled,
             };'
        );

        $this->assertTrue($state['error'], 'A password failing the pattern is not marked as wrong.');
        $this->assertSame('Trop court', $state['message'], 'The form\'s own message is not the one shown.');
        $this->assertSame('plain_error', $state['describedBy'], 'The message carries no id derived from its field, so two fields on one form would overwrite each other\'s.');
        $this->assertTrue($state['submit'], 'The form can be submitted with a password that fails its own rule.');
    }

    // A second blur must replace the message rather than add to it
    public function testCorrectingAPasswordClearsItsMessageAndFreesTheSubmit(): void
    {
        $state = $this->form(
            'const input = root.querySelector("#plain");
             const blur = (value) => { input.value = value; input.dispatchEvent(new FocusEvent("blur")); };
             blur("short");
             blur("still-bad");
             const during = root.querySelectorAll(".error-message").length;
             blur("Correct1!");
             await new Promise((r) => setTimeout(r, 30));

             return { during, after: root.querySelectorAll(".error-message").length, success: input.classList.contains("success"), submit: root.querySelector("[type=submit]").disabled };'
        );

        $this->assertSame(1, $state['during'], 'Each blur adds another message under the field.');
        $this->assertSame(0, $state['after'], 'The message stays under a password that has since been corrected.');
        $this->assertTrue($state['success'], 'A valid password is not marked as such.');
        $this->assertFalse($state['submit'], 'The submit stays closed on a form that is now valid.');
    }

    // The whole reason the controller keeps a list of its fields: reading only the field just blurred would let a matching confirmation re-open a submit the password had closed
    public function testAMatchingConfirmationDoesNotReopenASubmitTheePasswordClosed(): void
    {
        $this->assertTrue(
            (bool) $this->form(
                'const plain = root.querySelector("#plain");
                 const confirm = root.querySelector("#confirm");
                 plain.value = "short";
                 plain.dispatchEvent(new FocusEvent("blur"));
                 confirm.value = "short";
                 confirm.dispatchEvent(new FocusEvent("blur"));
                 await new Promise((r) => setTimeout(r, 30));

                 return root.querySelector("[type=submit]").disabled;'
            ),
            'A confirmation matching an invalid password re-opened the submit, so the form posts a password its own rule refuses.'
        );
    }

    // A confirmation that does not match is the other half, and it names the field it has to match rather than assuming an id
    public function testAConfirmationIsCheckedAgainstTheFieldItNames(): void
    {
        $state = $this->form(
            'const plain = root.querySelector("#plain");
             const confirm = root.querySelector("#confirm");
             plain.value = "Correct1!";
             plain.dispatchEvent(new FocusEvent("blur"));
             confirm.value = "Different1!";
             confirm.dispatchEvent(new FocusEvent("blur"));
             await new Promise((r) => setTimeout(r, 30));

             return { message: root.querySelector("#confirm_error")?.textContent ?? null, submit: root.querySelector("[type=submit]").disabled };'
        );

        $this->assertNotNull($state['message'], 'A confirmation that does not match says nothing.');
        $this->assertTrue($state['submit'], 'The form can be submitted with a confirmation that does not match.');
    }

    // The message goes after the wrapper the eye built, not inside it, which would push the icon off the input
    public function testTheMessageIsPlacedAfterTheWrapperAndNotInsideIt(): void
    {
        $this->assertTrue(
            (bool) $this->form(
                'const input = root.querySelector("#plain");
                 input.value = "short";
                 input.dispatchEvent(new FocusEvent("blur"));
                 await new Promise((r) => setTimeout(r, 30));

                 return root.querySelector(".error-message").previousElementSibling.classList.contains("has-toggle");'
            ),
            'The message was placed inside the wrapper holding the eye, which pushes the icon away from its input.'
        );
    }

    private function form(string $probe): mixed
    {
        return $this->observe(
            '<form data-controller="password">
                <label for="plain">Password</label>
                <input type="password" id="plain" name="plain" autocomplete="new-password" data-password-pattern="^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$" data-password-message="Trop court">
                <input type="password" id="confirm" name="confirm" data-password-confirm="plain">
                <button type="submit">Send</button>
            </form>',
            ['password' => 'password'],
            $probe
        );
    }
}
