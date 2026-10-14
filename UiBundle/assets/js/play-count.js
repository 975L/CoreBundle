/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { read } from "./favorite-store.js";

// Counts one play when the recording or film inside reaches its end (see PlayController). The end is 95% of it rather than the "ended" event: a listener stopping on the credits has heard the story. Under the token this browser already holds for its favorites, if any - none is ever made up here
export default class extends Controller {
    static values = { url: String };

    // Part of the length past which it was played to its end
    static END = 0.95;

    connect() {
        this.media = this.element.querySelector("audio, video");
        if (!this.media) {
            return;
        }
        this.counted = false;
        this.onTimeUpdate = () => this.follow();
        this.media.addEventListener("timeupdate", this.onTimeUpdate);
    }

    disconnect() {
        this.media?.removeEventListener("timeupdate", this.onTimeUpdate);
    }

    // Once per playthrough: played again from its first half, it will count again
    follow() {
        const { currentTime, duration } = this.media;
        if (!Number.isFinite(duration) || 0 === duration) {
            return;
        }
        if (currentTime < duration / 2) {
            this.counted = false;

            return;
        }
        if (!this.counted && currentTime >= duration * this.constructor.END) {
            this.counted = true;
            this.send();
        }
    }

    // Nothing is said when it fails: a play the network lost is a play not counted, which nobody needs telling
    send() {
        const token = read().token;
        fetch(this.urlValue, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(null === token ? {} : { token }),
        }).catch(() => {});
    }
}
