/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import Handlers from "./handlers.js";

// Show/hide toggle, format check and confirmation match, driven by data attributes any form can opt into
const LEGACY_PASSWORD_ID = "registration_form_plainPassword";
const LEGACY_CONFIRM_ID = "registration_form_confirmPassword";
const DEFAULT_PATTERN = "^(?=.*\\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}$";
const ICON_PATH = "/bundles/c975lui/icons/";

export default class extends Controller {
    connect() {
        // Every field this controller watches, so the submit button reads all of them and not just the last one blurred
        this.fields = [];

        // Executed immediately rather than on a DOM-ready event, for Turbo compatibility
        this.togglePasswordVisibility();
        this.validatePasswordFormat();
        this.validatePasswordConfirmation();
    }

    // Idempotent: an input already wrapped is skipped, so a Turbo re-connect never stacks a second eye
    togglePasswordVisibility() {
        this.element.querySelectorAll('input[type="password"]').forEach((passwordInput) => {
            if (passwordInput.closest(".has-toggle")) {
                return;
            }

            // What the form declared, "new-password" on a sign-up among others, put back verbatim once the password is hidden again
            const initialAutocomplete = passwordInput.getAttribute("autocomplete");

            // Wraps only the input (not its label/help text) so the toggle icon stays vertically centered on the input itself
            const wrapper = document.createElement("div");
            wrapper.classList.add("has-toggle");
            passwordInput.parentNode.insertBefore(wrapper, passwordInput);
            wrapper.appendChild(passwordInput);

            // Defines toggle
            const toggle = document.createElement("span");
            toggle.classList.add("toggle-password");

            // Adds image
            const image = document.createElement("img");
            image.src = ICON_PATH + "eye.svg";
            image.width = 20;
            image.height = 20;
            image.alt = "";
            toggle.appendChild(image);

            // Appends the toggle right after the input, inside the wrapper
            wrapper.appendChild(toggle);

            // Handles the click on the toggle
            toggle.addEventListener("click", () => {
                if (passwordInput.type === "password") {
                    passwordInput.type = "text";
                    passwordInput.setAttribute("autocomplete", "off");
                    image.src = ICON_PATH + "eye-slash.svg";
                } else {
                    passwordInput.type = "password";

                    if (null === initialAutocomplete) {
                        passwordInput.removeAttribute("autocomplete");
                    } else {
                        passwordInput.setAttribute("autocomplete", initialAutocomplete);
                    }

                    image.src = ICON_PATH + "eye.svg";
                }
            });
        });
    }

    // Checks the password format on blur, on any field carrying data-password-pattern
    validatePasswordFormat() {
        this.element.querySelectorAll("[data-password-pattern]").forEach((input) => {
            const pattern = new RegExp(input.dataset.passwordPattern || DEFAULT_PATTERN);
            const message = input.dataset.passwordMessage || Handlers.translate("form.password.error");

            this.fields.push(input);
            input.addEventListener("blur", () => {
                this.report(input, pattern.test(input.value), message);
            });
        });

        // Legacy field ids, kept until the next major; opt into data-password-pattern instead
        const legacy = this.legacyField(LEGACY_PASSWORD_ID, "passwordPattern");
        if (legacy) {
            const pattern = new RegExp(DEFAULT_PATTERN);
            this.fields.push(legacy);
            legacy.addEventListener("blur", () => {
                this.report(legacy, pattern.test(legacy.value), Handlers.translate("form.password.error"));
            });
        }
    }

    // Checks the confirmation matches, on any field whose data-password-confirm names the field to match
    validatePasswordConfirmation() {
        this.element.querySelectorAll("[data-password-confirm]").forEach((input) => {
            const source = document.getElementById(input.dataset.passwordConfirm);
            if (!source) {
                return;
            }

            const message = input.dataset.passwordMessage || Handlers.translate("form.password.confirmation.error");

            this.fields.push(input);
            input.addEventListener("blur", () => {
                this.report(input, source.value === input.value, message);
            });
        });

        // Same legacy fallback as above, on the confirmation field this time
        const legacy = this.legacyField(LEGACY_CONFIRM_ID, "passwordConfirm");
        const source = document.getElementById(LEGACY_PASSWORD_ID);
        if (legacy && source) {
            this.fields.push(legacy);
            legacy.addEventListener("blur", () => {
                this.report(legacy, source.value === legacy.value, Handlers.translate("form.password.confirmation.error"));
            });
        }
    }

    // Skips a field that already opted into the data attribute, so a migrated form isn't bound twice
    legacyField(id, dataKey) {
        const input = document.getElementById(id);

        return input && undefined === input.dataset[dataKey] ? input : null;
    }

    // Paints the field's state and disables the submit button while it is invalid
    report(input, isValid, message) {
        // After the ".has-toggle" wrapper, not inside it, which would push the eye icon down
        const anchor = input.closest(".has-toggle") || input;
        const errorId = (input.id || input.name || "password") + "_error";
        anchor.parentNode.querySelector(":scope > #" + CSS.escape(errorId))?.remove();
        input.classList.toggle("error", !isValid);
        input.classList.toggle("success", isValid);

        if (!isValid) {
            const errorMessage = document.createElement("p");
            errorMessage.id = errorId;
            errorMessage.classList.add("error-message");
            errorMessage.textContent = message;
            anchor.parentNode.insertBefore(errorMessage, anchor.nextSibling);
        }

        this.refreshSubmit(input.form);
    }

    // Reads every watched field of the form, a matching confirmation alone never re-opening a submit an invalid password closed
    refreshSubmit(form) {
        // The submit button sits outside the wrapper the toggle built, so it is looked up on the form itself
        const submitButton = form?.querySelector('input[type="submit"], button[type="submit"]');
        if (!submitButton) {
            return;
        }

        // A field never blurred yet carries neither class, so it holds nothing back
        submitButton.disabled = this.fields.filter((field) => field.form === form).some((field) => field.classList.contains("error"));
    }
}
