/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Each captcha provider's browser side, by the slug its PHP provider answers (see CaptchaProviderInterface): load(siteKey) resolving once it can mint, token(siteKey, action) resolving to the token
const DRIVERS = {
    recaptcha: () => import("./captcha-recaptcha.js"),
};

// Fills in c975L\UiBundle\Form\CaptchaType's hidden field with the provider's token, without costing anything to a visitor who never touches the form: its script is only fetched on the first interaction with the form, and the token itself is only asked for on submit - which also makes it always fresh, where a token grabbed on page load may expire. Generic: loading and minting are the driver's business
export default class extends Controller {
    static values = { provider: String, siteKey: String, action: String };

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

    // Loads the provider's driver and script once, resolving to the driver when it can mint a token. An unknown provider rejects, which submits the form with an empty token for the server to judge
    warmUp() {
        if (!this.loading) {
            const driver = DRIVERS[this.providerValue];
            this.loading = (driver ? driver() : Promise.reject(new Error(`Unknown captcha provider "${this.providerValue}".`)))
                .then(async ({ default: captcha }) => {
                    await captcha.load(this.siteKeyValue);

                    return captcha;
                })
                // A failed load must not be cached: a blocker lifted, a network back up, and the next submit deserves its own script tag rather than replaying this rejection forever
                .catch((error) => {
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
            const captcha = await this.warmUp();
            this.element.value = await captcha.token(this.siteKeyValue, this.actionValue);
        } catch {
            // Blocked, offline or refused by the provider: the form is submitted all the same, with an empty token. Deciding what an unverifiable submission is worth belongs to the server (see CaptchaValidator), not to a visitor stuck on a page that silently refuses to submit
        }

        // Reset right after: requestSubmit() dispatches "submit" synchronously (the pass this flag lets through), then either navigates away - in which case nothing below matters - or was stopped by the browser's own field validation, and the next attempt must fetch a fresh token rather than reuse one that may have expired in the meantime
        this.submitting = true;
        this.form.requestSubmit(submitter);
        this.submitting = false;
    }
}
