/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Where this browser keeps who it votes as, and what it voted. localStorage rather than a cookie: the token has no business travelling in a header on every request to the site, and a Set-Cookie would be answered on pages that are cached and shared between visitors
const STORE = "c975l-rating";

export default class extends Controller {
    static targets = ["row", "star", "tally"];

    static values = {
        url: String,
        key: String,
        scale: Number,
        count: Number,
        average: Number,
        compact: Boolean,
        noneLabel: String,
        oneLabel: String,
        manyLabel: String,
        errorLabel: String,
    };

    // Reads only - nothing is written to the browser's storage until the visitor actually clicks, which is what keeps this out of consent territory: no identifier is created for someone who merely reads the page
    connect() {
        this.sending = false;
        this.own = this.read().votes[this.keyValue] ?? null;
        if (null !== this.own) {
            this.paint();
        }
    }

    async vote(event) {
        // One vote at a time: two clicks in a row would send two votes the server reads as two first votes, and the second insert breaks on the unique index the voter is held by
        if (this.sending) {
            return;
        }

        const value = Number(event.params.value);
        const store = this.read();
        // The token is minted here, on the click, and not before
        store.token = store.token || this.newToken();

        // Raised only once what precedes it has succeeded, and lowered by the finally below: a browser refusing to mint a token (crypto.randomUUID() needs a secure context) would otherwise leave the flag up and the widget dead for the rest of the page
        this.sending = true;

        let answer;
        try {
            const response = await fetch(this.urlValue, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                // Same-origin only, which is also what the route checks: it takes no csrf token, so the browser must not be allowed to carry this anywhere else
                credentials: "same-origin",
                // The scale is not sent: it is the site's own setting, and the server reads it there
                body: JSON.stringify({ value: value, token: store.token }),
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            answer = await response.json();
        } catch {
            this.tallyTarget.textContent = this.errorLabelValue;

            return;
        } finally {
            this.sending = false;
        }

        // The server decides: re-sending the score already stored takes the vote back (which is the toggle a single-icon "like" needs), anything else replaces it
        this.own = answer.value;
        this.countValue = answer.count;
        this.averageValue = answer.average;

        if (null === answer.value) {
            delete store.votes[this.keyValue];
        } else {
            store.votes[this.keyValue] = answer.value;
        }
        this.write(store);

        this.paint();
        this.tallyTarget.textContent = this.tally();
    }

    // The row shows the visitor's own vote once they have one, the public average until then - what every rating widget does, and the tally underneath keeps saying what everyone else thinks
    paint() {
        const filled = null !== this.own ? this.own : Math.round(this.averageValue);
        this.starTargets.forEach((star, index) => {
            star.classList.toggle("rating-star--on", index < filled);
            star.setAttribute("aria-pressed", String(null !== this.own && index < this.own));
        });
        this.element.classList.toggle("rating-vote--voted", null !== this.own);
    }

    tally() {
        // A compact widget says nothing rather than "not rated yet": the empty row of icons already says it, and the sentence would take the width of the card it sits in
        if (0 === this.countValue) {
            return this.compactValue ? "" : this.noneLabelValue;
        }

        const label = (1 === this.countValue ? this.oneLabelValue : this.manyLabelValue).replace("%count%", String(this.countValue));

        // A single icon is a "like": the average of a column of ones says nothing, the count says everything - which is also why a compact widget keeps the count here and drops it everywhere else
        if (1 === this.scaleValue) {
            return label;
        }

        return this.compactValue ? `${this.averageValue}/${this.scaleValue}` : `${this.averageValue}/${this.scaleValue} - ${label}`;
    }

    // 32 hex characters, the shape RatingService::resolveVoter() accepts
    newToken() {
        return window.crypto.randomUUID().replace(/-/g, "");
    }

    // Storage can be unavailable (private browsing, a browser configured to refuse it): the widget then votes as a fresh visitor each time rather than throwing, which is the same degradation as a cleared browser
    read() {
        try {
            const stored = JSON.parse(window.localStorage.getItem(STORE) || "{}");

            return { token: stored.token || null, votes: stored.votes || {} };
        } catch {
            return { token: null, votes: {} };
        }
    }

    write(store) {
        try {
            window.localStorage.setItem(STORE, JSON.stringify(store));
        } catch {
            // Nothing to do: the vote is recorded server-side either way, only its display on a later visit is lost
        }
    }
}
