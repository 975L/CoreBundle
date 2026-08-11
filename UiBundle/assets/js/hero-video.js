/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// A hero's background video plays through this controller rather than through an "autoplay" attribute, which no stylesheet and no preference can take back once the browser has honored it. Nothing else has to run for the section to hold: a hero whose script never loads keeps the video's own first frame, a still picture over which the title reads exactly as it does over a background image
export default class extends Controller {
    connect() {
        this.motion = window.matchMedia("(prefers-reduced-motion: reduce)");
        // Bound once, the same reference being what removeEventListener() needs on disconnect
        this.onMotionChange = () => this.apply();
        this.motion.addEventListener("change", this.onMotionChange);
        this.apply();
    }

    // Turbo caches the page as it stands, and the listener would otherwise outlive the element it drives
    disconnect() {
        this.motion.removeEventListener("change", this.onMotionChange);
    }

    // The background prints no pause control of any kind, so the preference is the only way out of it (WCAG 2.2.2).
    // Paused rather than hidden: the frame it stops on goes on filling the section, where hiding the video would bare the overlay under it whenever no image was uploaded beside it
    apply() {
        if (this.motion.matches) {
            this.element.pause();

            return;
        }

        // Rejected by a browser refusing to play it at all: nothing to recover, the first frame stays painted
        this.element.play().catch(() => {});
    }
}
