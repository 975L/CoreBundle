/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { createNoncedStyleElement } from "./nonced-style-element.js";

// c975l/site-bundle registers its own banner as "cookie-consent", but the Stimulus identifier is a free choice for whoever provides the banner - both the dasherized and the camelCase spelling are matched here so the contract can't silently break on a casing difference. A missed match is not a harmless no-op: connect() would conclude the page has no banner at all and render the iframe straight away, pulling ~1 MB of YouTube before any consent has been given.
const CONSENT_BANNER_SELECTOR = '[data-controller~="cookie-consent"], [data-controller~="cookieConsent"]';

// Only injects the iframe once the figure is about to enter the viewport - the player is ~1 MB of third-party JavaScript, which otherwise dominates the whole page's transfer weight for a visitor who never scrolls down to it. `iframe.loading = "lazy"` is not enough on its own: the browser only honours it for an iframe far enough down the page, and the element still belongs to the initial load when it is inserted on connect(). 200px of margin so the player is ready by the time it is actually scrolled into view.
const ROOT_MARGIN = "200px";

// Deliberately not coupled to any particular consent-banner implementation or bundle - reacts to an optional external contract instead: a banner element matching CONSENT_BANNER_SELECTOR present in the page, a `window.CookieConsent` global exposing vanilla-cookieconsent v3's API (https://cookieconsent.orestbida.com/), and its `cc:onConsent`/`cc:onChange` DOM events. c975l/site-bundle's `<twig:c975LSite:General:CookieConsent/>` is one such provider, but any consuming app's own banner satisfying the same contract works just as well - no composer dependency on it either way.
export default class extends Controller {
    static targets = ["placeholder"];
    static values = { src: String, title: String, width: String, height: String };

    connect() {
        this.onConsent = this.onConsent.bind(this);

        // No consent banner on this page - never block content on a site that doesn't use one
        if (!document.querySelector(CONSENT_BANNER_SELECTOR)) {
            this.scheduleIframe();
            return;
        }

        if (window.CookieConsent && window.CookieConsent.acceptedCategory("content")) {
            this.scheduleIframe();
            return;
        }

        // Consent not yet decided (or lib still loading) - wait for it. "cc:onConsent" fires on every page load once the user's choice is known (not just the first time), so returning visitors who already accepted still get the iframe without needing to click again
        window.addEventListener("cc:onConsent", this.onConsent);
        window.addEventListener("cc:onChange", this.onConsent);
    }

    disconnect() {
        this.stopListening();
        this.observer?.disconnect();
        this.sizingStyleEl?.remove();
    }

    stopListening() {
        window.removeEventListener("cc:onConsent", this.onConsent);
        window.removeEventListener("cc:onChange", this.onConsent);
    }

    accept() {
        // The banner element can render before its own script (which sets this global) finishes loading - a click in that window must be a no-op, not a thrown error
        window.CookieConsent?.acceptCategory("content");
    }

    onConsent() {
        if (window.CookieConsent?.acceptedCategory("content")) {
            this.scheduleIframe();
        }
    }

    // Consent is settled, the player may now be loaded - defers to the first time the figure nears the viewport (see ROOT_MARGIN). A visitor who clicks the placeholder's own "accept" button is looking straight at it, so the observer fires immediately in that case
    scheduleIframe() {
        this.stopListening();

        if (!("IntersectionObserver" in window)) {
            this.renderIframe();
            return;
        }

        this.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                this.renderIframe();
            }
        }, { rootMargin: ROOT_MARGIN });
        this.observer.observe(this.element);
    }

    renderIframe() {
        this.stopListening();
        this.observer?.disconnect();

        // Same 16/9 assumption as the pre-consent placeholder's CSS aspect-ratio (sass/_images.scss)
        // - only one side is ever configured in practice, so the other is derived to keep it from
        // falling back to the iframe's native 300x150 default and looking squashed/stretched
        const RATIO = 16 / 9;
        let width = this.widthValue ? parseInt(this.widthValue, 10) : null;
        let height = this.heightValue ? parseInt(this.heightValue, 10) : null;
        if (width && !height) {
            height = Math.round(width / RATIO);
        } else if (height && !width) {
            width = Math.round(height * RATIO);
        }

        // c975l/site-bundle's generic "iframe { width; aspect-ratio }" (sass/_iframe.scss) targets every
        // bare <iframe> site-wide - any CSS declaration (even "auto"/"revert") outranks the HTML width/
        // height *attributes* regardless of specificity (they're a lower-priority presentational hint,
        // not a normal cascade value), so setting iframe.width/.height here would still lose to it. A
        // per-instance id-scoped rule with real px values is the only thing specific enough to win outright.
        // Can't use iframe.style.width either - under this site's nonce-based CSP, a nonce never covers an
        // inline style *attribute* set from JS, only a real <style> element (see nonced-style-element.js).
        if (width && height) {
            if (!this.element.id) {
                this.element.id = `video-iframe-${Math.random().toString(36).slice(2)}`;
            }
            // height stays "auto" + aspect-ratio (not a fixed px value) so a narrow viewport, which
            // shrinks the width via .video-iframe-consent iframe's "max-width: 100%" (sass/_images.scss),
            // scales the height down proportionally instead of leaving it fixed and squashing the video
            this.sizingStyleEl = createNoncedStyleElement();
            this.sizingStyleEl.textContent = `#${CSS.escape(this.element.id)} iframe { width: ${width}px; height: auto; aspect-ratio: ${width} / ${height}; }`;
        }

        const iframe = document.createElement("iframe");
        iframe.src = this.srcValue;
        iframe.title = this.titleValue || "Video player";
        iframe.frameBorder = "0";
        iframe.allowFullscreen = true;
        iframe.loading = "lazy";
        this.element.replaceChildren(iframe);
    }
}
