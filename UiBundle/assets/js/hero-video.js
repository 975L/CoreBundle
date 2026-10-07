/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// A video playing by itself - a hero's background, a book's flipbook - plays through this controller rather than through an "autoplay" attribute, which no stylesheet and no preference can take back once the browser has honored it, and which fetches the whole file with the page. Nothing else has to run for the page to hold: a video whose script never loads keeps its own first frame, a still picture over which the title reads exactly as it does over a background image
export default class extends Controller {
    connect() {
        this.motion = window.matchMedia("(prefers-reduced-motion: reduce)");
        this.visible = false;
        // Bound once, the same reference being what removeEventListener() needs on disconnect
        this.onMotionChange = () => this.apply();
        this.motion.addEventListener("change", this.onMotionChange);
        // Played only while on screen: a flipbook further down the page is not downloaded before anyone scrolls to it, and one scrolled past stops spending the visitor's data
        this.observer = new IntersectionObserver((entries) => {
            this.visible = entries[entries.length - 1].isIntersecting;
            this.apply();
        });
        this.observer.observe(this.element);
    }

    // Turbo caches the page as it stands, and the listener would otherwise outlive the element it drives
    disconnect() {
        this.motion.removeEventListener("change", this.onMotionChange);
        this.observer.disconnect();
    }

    // The background prints no pause control of any kind, so the preference is the only way out of it (WCAG 2.2.2).
    // Paused rather than hidden: the frame it stops on goes on filling the section, where hiding the video would bare the overlay under it whenever no image was uploaded beside it
    apply() {
        if (this.motion.matches || !this.visible) {
            this.element.pause();

            return;
        }

        // Rejected by a browser refusing to play it at all: nothing to recover, the first frame stays painted
        this.element.play().catch(() => {});
    }
}
