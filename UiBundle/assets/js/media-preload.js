/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// A player's metadata - a video's first frame, a recording's duration - fetched once it comes on screen rather than with the page: this delays the request, it does not shrink it, so a player on screen still pulls a whole mp3 or a fragmented mp4 to measure its duration, and only one further down is spared until reached
export default class extends Controller {
    connect() {
        this.observer = new IntersectionObserver((entries) => {
            if (!entries[entries.length - 1].isIntersecting) {
                return;
            }

            // Raising the preload is enough for the browser to fetch what the first frame needs
            this.element.preload = "metadata";
            this.observer.disconnect();
        });
        this.observer.observe(this.element);
    }

    disconnect() {
        this.observer.disconnect();
    }
}
