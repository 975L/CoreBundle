/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { read } from "./favorite-store.js";

// Counts one play when enough of the recording or film inside was played (see PlayController): a ticket asked for as it starts, handed back once the part the site sets is reached, the server alone measuring whether that much time went by - jumping ahead counts nothing. Under the token this browser already holds for its favorites, if any - none is ever made up here. Says so with a "ui-play-count:counted" event carrying the ticket, for whatever else the site gives for a play
export default class extends Controller {
    static values = { url: String, startUrl: String };

    connect() {
        // The recording first: a reader may print a video page before its player, and a film alone is still followed
        this.media = this.element.querySelector("audio") ?? this.element.querySelector("video");
        if (!this.media) {
            return;
        }
        this.counted = false;
        this.ticket = null;
        this.onTimeUpdate = () => this.follow();
        this.media.addEventListener("timeupdate", this.onTimeUpdate);
    }

    disconnect() {
        this.media?.removeEventListener("timeupdate", this.onTimeUpdate);
    }

    // Once per playthrough: taken back under half the part to be played, it starts a new one, with a new ticket
    follow() {
        const { currentTime, duration } = this.media;
        if (!Number.isFinite(duration) || 0 === duration) {
            return;
        }
        if (this.counted) {
            if (currentTime < (duration * this.ratio) / 2) {
                this.counted = false;
                this.ticket = null;
            }

            return;
        }
        if (null === this.ticket) {
            this.start();

            return;
        }
        if ("string" === typeof this.ticket && currentTime >= duration * this.ratio) {
            this.counted = true;
            this.send(this.ticket);
        }
    }

    // Asked once per playthrough: false while it is on its way, and kept false when refused, so a page turned down does not ask four times a second
    async start() {
        this.ticket = false;
        try {
            const response = await fetch(this.startUrlValue, this.post({}));
            const answer = response.ok ? await response.json() : null;
            if (answer?.ticket) {
                this.ticket = answer.ticket;
                this.ratio = answer.ratio;
            }
        } catch {
            // A playthrough the network lost is not counted, which nobody needs telling
        }
    }

    async send(ticket) {
        try {
            const response = await fetch(this.urlValue, this.post({ ticket }));
            if (response.ok) {
                this.dispatch("counted", { detail: { ticket } });
            }
        } catch {
            // Nothing is said when it fails, for the same reason
        }
    }

    post(body) {
        const token = read().token;

        return {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(null === token ? body : { ...body, token }),
        };
    }
}
