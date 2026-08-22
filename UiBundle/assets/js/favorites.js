/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { read, sync } from "./favorite-store.js";

// The wishlist page: an empty shell served like any other page - cached, shared, holding nothing personal - into which this fetches the visitor's own cards
export default class extends Controller {
    static targets = ["list", "empty", "loading"];

    static values = {
        url: String,
        errorLabel: String,
    };

    async connect() {
        let answer;
        try {
            const response = await fetch(this.urlValue, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                credentials: "same-origin",
                body: JSON.stringify({ token: read().token }),
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            answer = await response.json();
        } catch {
            this.loadingTarget.textContent = this.errorLabelValue;

            return;
        }

        // What the server holds, written over what this browser assumed: one visit here and every heart of the site paints right, which is the whole of how a list follows an account onto a device that never saw it
        sync(answer.keys);

        // The navbar's own count hears it from the very event a card's heart announces a click with: a browser whose storage was behind would otherwise keep showing what it had assumed until the next click
        this.dispatch("changed", { detail: { count: answer.count }, prefix: "ui-favorite", bubbles: true });

        this.loadingTarget.hidden = true;
        this.emptyTarget.hidden = answer.count > 0;
        // Rendered by the server: the cards are the site's own, and a wishlist drawing them in javascript would be a second card to keep in step with the first
        this.listTarget.innerHTML = answer.html;
    }
}
