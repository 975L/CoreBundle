/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Fills in c975L\UiBundle\Form\CaptchaType's hidden field with a reCAPTCHA v3 token, without costing anything to a visitor who never touches the form. Google's api.js weighs ~765 KB and ~1.5 s of main thread, so it is only fetched on the first interaction with the form, and the token itself is only asked for on submit - which also makes it always fresh, where a token grabbed on page load expires after two minutes
export default class extends Controller {
    static values = { siteKey: String, action: String };

    connect() {
        this.form = this.element.form;
        if (!this.form) {
            return;
        }

        this.warmUp = this.warmUp.bind(this);
        this.onSubmit = this.onSubmit.bind(this);

        // Fire-and-forget: nothing waits on this pre-load, and a blocked api.js must not surface as an unhandled rejection on every page view - onSubmit calls warmUp() again and handles the failure itself
        this.preload = () => this.warmUp().catch(() => {});

        // Starts the download behind the visitor's typing, so submitting doesn't wait on a cold request. "once" as this only ever needs to happen a single time
        this.form.addEventListener("focusin", this.preload, { once: true });
        this.form.addEventListener("pointerdown", this.preload, { once: true });
        this.form.addEventListener("submit", this.onSubmit);
    }

    disconnect() {
        this.form?.removeEventListener("focusin", this.preload);
        this.form?.removeEventListener("pointerdown", this.preload);
        this.form?.removeEventListener("submit", this.onSubmit);
    }

    // Injects Google's api.js once, and resolves when grecaptcha is usable
    warmUp() {
        if (!this.loading) {
            this.loading = new Promise((resolve, reject) => {
                const script = document.createElement("script");
                script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(this.siteKeyValue)}`;
                script.async = true;
                script.defer = true;
                script.addEventListener("load", () => (window.grecaptcha ? window.grecaptcha.ready(resolve) : reject()));
                script.addEventListener("error", reject);
                document.head.appendChild(script);
            // A failed load must not be cached: a blocker lifted, a network back up, and the next submit deserves its own script tag rather than replaying this rejection forever
            }).catch((error) => {
                this.loading = null;
                throw error;
            });
        }

        return this.loading;
    }

    async onSubmit(event) {
        // Second pass, the one this controller triggered itself once the token was in place
        if (this.submitting) {
            return;
        }

        // Another listener already cancelled this submission (a consumer's own validation, a confirm dialog...): calling requestSubmit() below would override that decision
        if (event.defaultPrevented) {
            return;
        }

        event.preventDefault();
        const submitter = event.submitter;

        try {
            await this.warmUp();
            this.element.value = await window.grecaptcha.execute(this.siteKeyValue, { action: this.actionValue });
        } catch {
            // Blocked, offline or refused by Google: the form is submitted all the same, with an empty token. Deciding what an unverifiable submission is worth belongs to the server (see CaptchaValidator), not to a visitor stuck on a page that silently refuses to submit
        }

        // Reset right after: requestSubmit() dispatches "submit" synchronously (the pass this flag lets through), then either navigates away - in which case nothing below matters - or was stopped by the browser's own field validation, and the next attempt must fetch a fresh token rather than reuse one that may have expired in the meantime
        this.submitting = true;
        this.form.requestSubmit(submitter);
        this.submitting = false;
    }
}
