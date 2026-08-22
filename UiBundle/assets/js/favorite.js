/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { read, write, newToken } from "./favorite-store.js";

// The heart on a product, a book, a photo. The page carrying it is cached and shared between visitors, so nothing about this visitor's own list is printed in its html: it is read from their own browser here, and corrected by the answer to their next click
export default class extends Controller {
    static targets = ["button"];

    static values = {
        url: String,
        key: String,
        addLabel: String,
        removeLabel: String,
        errorLabel: String,
    };

    // Reads only - nothing is written to the browser's storage until the visitor actually puts something aside
    connect() {
        this.sending = false;
        this.paint(read().keys[this.keyValue] === true);
    }

    async toggle() {
        // One click at a time: two in a row would send two toggles, and the second would take back what the first put aside
        if (this.sending) {
            return;
        }

        const store = read();
        // The token is minted here, on the click, and not before
        store.token = store.token || newToken();

        // Raised only once what precedes it has succeeded, and lowered by the finally below: a browser refusing to mint a token (crypto.randomUUID() needs a secure context) would otherwise leave the flag up and the heart dead for the rest of the page
        this.sending = true;

        let answer;
        try {
            const response = await fetch(this.urlValue, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                // Same-origin only, which is also what the route checks: it takes no csrf token, so the browser must not be allowed to carry this anywhere else
                credentials: "same-origin",
                body: JSON.stringify({ token: store.token }),
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            answer = await response.json();
        } catch {
            this.buttonTarget.setAttribute("aria-label", this.errorLabelValue);

            return;
        } finally {
            this.sending = false;
        }

        // The server decides, and this browser records what it decided: an account holding the thing already answers "removed" where the empty heart would have assumed "added"
        if (answer.favorited) {
            store.keys[this.keyValue] = true;
        } else {
            delete store.keys[this.keyValue];
        }
        write(store);

        this.paint(answer.favorited);
        // Whoever is showing how many things are put aside - a navbar icon, a page heading - hears it from here rather than being handed a reference to this controller
        this.dispatch("changed", { detail: { count: answer.count }, prefix: "ui-favorite", bubbles: true });
    }

    paint(favorited) {
        this.buttonTarget.classList.toggle("favorite-button--on", favorited);
        this.buttonTarget.setAttribute("aria-pressed", favorited ? "true" : "false");
        this.buttonTarget.setAttribute("aria-label", favorited ? this.removeLabelValue : this.addLabelValue);
    }
}
