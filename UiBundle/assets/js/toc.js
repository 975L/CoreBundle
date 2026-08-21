/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["link"];

    connect() {
        this.sections = new Map();
        this.visible = new Set();

        for (const link of this.linkTargets) {
            const section = document.getElementById(link.dataset.tocAnchor);

            if (section) {
                this.sections.set(section, link);
            }
        }

        // A band across the upper third of the screen, stated in percentages rather than in measured pixels: a nonced style-src drops any rule a script writes onto an element (see NoncedStyleSrcTest), so nothing here reads a layout to write one back. The room left above a section is the stylesheet's alone, through .toc-target
        this.observer = new IntersectionObserver(this.update.bind(this), {
            rootMargin: "-20% 0px -70% 0px",
        });

        for (const section of this.sections.keys()) {
            this.observer.observe(section);
        }
    }

    // Turbo caches the page as it stands, so the marks are undone rather than left frozen in a snapshot restored before this controller connects again
    disconnect() {
        this.observer?.disconnect();

        for (const link of this.sections.values()) {
            this.mark(link, false);
        }
    }

    update(records) {
        for (const record of records) {
            record.isIntersecting ? this.visible.add(record.target) : this.visible.delete(record.target);
        }

        // Several sections cross the band at once while scrolling - the one being read is the first of them in the page's own order, not the last the observer happened to report
        let current = null;

        for (const section of this.sections.keys()) {
            if (this.visible.has(section)) {
                current = section;
                break;
            }
        }

        // Between two sections - and at the top of a page whose first one starts below the band - nothing is crossing it. The entry last lit stays lit: an empty summary reads as broken where the reader has simply not reached the next section yet
        if (!current) {
            return;
        }

        for (const [section, link] of this.sections) {
            this.mark(link, section === current);
        }

        // The chips scroll sideways on a phone, where the entry that just lit up is as often as not off screen
        this.sections.get(current).scrollIntoView({ block: "nearest", inline: "nearest" });
    }

    mark(link, isCurrent) {
        link.classList.toggle("toc-link--current", isCurrent);

        // "true" and not "location": the anchor is where the reader is, not the address the browser is on
        isCurrent ? link.setAttribute("aria-current", "true") : link.removeAttribute("aria-current");
    }
}
