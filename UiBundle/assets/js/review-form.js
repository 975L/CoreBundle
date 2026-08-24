/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Brings the review form under the thing it is about, the first time the fold is opened. Fetched and not printed with the page: the form carries a csrf token, and the token carries a Set-Cookie no reader of the page should be served (see ReviewController)
export default class extends Controller {
    static targets = ["link", "panel"];

    static values = {
        url: String,
        errorLabel: String,
    };

    // Bound to the fold itself and not to what opens it: a summary is opened by a click on the label, by a keyboard, and by the link below - all three end up here
    connect() {
        this.loaded = false;
        this.element.addEventListener("toggle", this.toggled);
        if (this.hasLinkTarget) {
            this.linkTarget.addEventListener("click", this.open);
        }
    }

    disconnect() {
        this.element.removeEventListener("toggle", this.toggled);
        if (this.hasLinkTarget) {
            this.linkTarget.removeEventListener("click", this.open);
        }
    }

    toggled = () => {
        if (this.element.open) {
            this.load();
        }
    };

    // The link is what a browser running no javascript follows, and it leads to the page holding this very form; here the fold already has room for it
    open = (event) => {
        event.preventDefault();
        this.element.open = !this.element.open;
    };

    // Fetched once: a form already on screen is left exactly as the visitor filled it, closing and reopening the fold costing nothing
    async load() {
        if (this.loaded) {
            return;
        }
        this.loaded = true;

        try {
            // The header is what tells the route to answer with the form alone rather than with a whole page
            const response = await fetch(this.urlValue, {
                headers: { "X-Requested-With": "XMLHttpRequest" },
                credentials: "same-origin",
            });

            if (!response.ok) {
                throw new Error(response.status);
            }

            this.panelTarget.innerHTML = await response.text();
        } catch {
            // Reopening then retries, the visitor having the plain link under the summary either way
            this.loaded = false;
            this.panelTarget.textContent = this.errorLabelValue;
        }
    }
}
