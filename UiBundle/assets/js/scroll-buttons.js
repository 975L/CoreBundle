/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The two buttons pulling to the top and to the bottom of the page, and the scrolling of every same-page anchor - done here rather than by Turbo, which treats a same-page "#anchor" as a full visit
export default class extends Controller {
    static targets = ["top", "bottom"];

    // Past a screenful, which is when the page has enough above or below to be worth jumping over
    static AMOUNT_SCROLLED = 300;

    connect() {
        this.scrollToAnchor = this.scrollToAnchor.bind(this);
        this.toggle = this.toggle.bind(this);

        // Bound on the document rather than on this element: an anchor is clicked anywhere on the page, and only the two buttons sit here
        document.addEventListener("click", this.scrollToAnchor);
        window.addEventListener("scroll", this.toggle);
        this.toggle();
    }

    disconnect() {
        document.removeEventListener("click", this.scrollToAnchor);
        window.removeEventListener("scroll", this.toggle);
    }

    // Scrolled here rather than by Turbo, which treats a same-page "#anchor" as a full visit
    scrollToAnchor(event) {
        const link = event.target.closest('a[href*="#"]');
        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.href);
        // The query is part of what tells the two apart: a link changing it asks for another listing - an order, a page, a filter - and only looks like an anchor because it ends on one, and an origin of its own is no anchor of this page either, same path or not
        if (url.origin !== window.location.origin || url.pathname !== window.location.pathname || url.search !== window.location.search || url.hash === "") {
            return;
        }

        const target = document.getElementById(url.hash.slice(1));
        if (!target) {
            return;
        }

        event.preventDefault();
        // The whole address rather than the hash alone: a relative url resolves against the <base href> every page carries, which would leave the address stripped of its own path
        history.pushState(null, "", url.href);
        // A listing growing during the scroll moves the target away before it is reached: the infiniteScroll controller pauses on this event, and resumes as soon as the visitor scrolls by themselves
        document.dispatchEvent(new CustomEvent("anchor:scroll"));
        target.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    // Each button appears once there is something to go back to, or something left below
    toggle() {
        const amount = this.constructor.AMOUNT_SCROLLED;

        if (this.hasTopTarget) {
            this.display(this.topTarget, window.scrollY > amount);
        }

        if (this.hasBottomTarget) {
            this.display(this.bottomTarget, window.scrollY + window.innerHeight + amount < document.body.scrollHeight);
        }
    }

    // By the class alone, never by an inline style, which a nonced style-src drops: sass/_scroll-buttons.scss lays the button out on both classes and hides it while it carries neither
    display(button, shown) {
        if (shown) {
            button.classList.remove("fade-out");
            button.classList.add("fade-in");
            return;
        }

        // Only a button that came in fades out: fading one that was never shown is the flash the page opened on
        if (button.classList.contains("fade-in")) {
            button.classList.remove("fade-in");
            button.classList.add("fade-out");
        }
    }
}
