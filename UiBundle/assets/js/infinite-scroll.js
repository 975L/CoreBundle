/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Grows a paginated listing as the visitor scrolls, by fetching the page its "next" link already points to and appending the items found there. Nothing is hidden and no route is added: the link stays an ordinary link to the next page, which is what a crawler follows and what happens without javascript or after a failed fetch
export default class extends Controller {
    static targets = ["list", "next", "count"];

    // The gestures a visitor scrolls with, which is what tells a paused listing that it is being read again
    static RESUME_EVENTS = ["wheel", "touchstart", "keydown", "pointerdown"];

    connect() {
        if (!this.hasListTarget || !this.hasNextTarget) {
            return;
        }

        this.observer = new IntersectionObserver(
            (entries) => { if (entries[0].isIntersecting) { this.load(); } },
            // Fires before the link is reached, so the items are in place by the time the visitor scrolls to them
            { rootMargin: "600px" }
        );
        this.pause = this.pause.bind(this);
        this.resume = this.resume.bind(this);
        document.addEventListener("anchor:scroll", this.pause);
        this.watch();
    }

    disconnect() {
        this.observer?.disconnect();
        document.removeEventListener("anchor:scroll", this.pause);
        this.constructor.RESUME_EVENTS.forEach((type) => document.removeEventListener(type, this.resume));
    }

    // A scroll heading for an anchor - the button pulling to the bottom of the page - reaches a place that appending items would push away from it, and the visitor asking for the footer is not asking for more of the listing
    pause() {
        if (!this.hasNextTarget) {
            return;
        }

        this.observer?.disconnect();
        this.constructor.RESUME_EVENTS.forEach((type) => document.addEventListener(type, this.resume, { once: true, passive: true }));
    }

    // Scrolling again is the visitor coming back to the listing: only one of the gestures fires, so the others are taken off here
    resume() {
        this.constructor.RESUME_EVENTS.forEach((type) => document.removeEventListener(type, this.resume));

        if (this.hasNextTarget) {
            this.watch();
        }
    }

    // Also bound on the link itself, so a click loads in place rather than leaving the page
    load(event) {
        event?.preventDefault();

        if (this.loading || !this.hasNextTarget) {
            return;
        }

        this.loading = true;
        const url = this.nextTarget.href;
        this.nextTarget.setAttribute("aria-busy", "true");

        fetch(url, { headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then((response) => response.ok ? response.text() : Promise.reject(response.status))
            .then((html) => this.append(html, url, undefined !== event))
            // The link is left where it is, so the visitor can retry with a click, but the observer stops: a failure the scroll keeps re-firing would only repeat the same request
            .catch(() => this.observer?.disconnect())
            .finally(() => {
                this.loading = false;
                if (this.hasNextTarget) {
                    this.nextTarget.removeAttribute("aria-busy");
                }
            });
    }

    // Only the items are taken from the fetched page: appending its listing whole would bring a second heading and a second list along with them
    append(html, url, focus) {
        const page = new DOMParser().parseFromString(html, "text/html");
        const list = page.querySelector('[data-infiniteScroll-target="list"]');

        if (!list || 0 === list.children.length) {
            this.end();
            return;
        }

        const first = list.firstElementChild;
        this.listTarget.append(...list.children);

        if (this.hasCountTarget) {
            this.countTarget.textContent = this.listTarget.children.length;
        }

        // Read as an attribute and resolved against the page it comes from: the address bar still shows the first page, which is not what the next one is relative to
        const next = page.querySelector('[data-infiniteScroll-target="next"]')?.getAttribute("href");
        if (!next) {
            this.end();
            return;
        }

        this.nextTarget.href = new URL(next, url).href;
        this.watch();

        // A click leaves the focus on the link, which the loaded items now sit above - a keyboard visitor would otherwise have to walk back up to them
        if (focus) {
            (first.matches("a, button") ? first : first.querySelector("a, button"))?.focus();
        }
    }

    // An observer reports changes of state, and the link often stays in view when the items appended under it are few - asking it again for the state it is already in is what keeps a tall screen filling
    watch() {
        this.observer?.unobserve(this.nextTarget);
        this.observer?.observe(this.nextTarget);
    }

    // Nothing left to load: the link goes, and with it the element the observer watches
    end() {
        this.observer?.disconnect();
        this.constructor.RESUME_EVENTS.forEach((type) => document.removeEventListener(type, this.resume));
        if (this.hasNextTarget) {
            this.nextTarget.remove();
        }
    }
}
