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

// What a platform's player needs to work once it is framed. Not autoplay on purpose: a returning visitor who already accepted gets the iframe injected on scroll, and a src carrying the platform's own autoplay parameter would then start playing at them unasked
const ALLOW = "accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture";

// A nested browsing context takes its initial referrer policy from the framing element, so leaving this off hands the player whatever the embedding page declares - a site sending no referrer at all has its videos refused by the platforms' media servers, which check the domain they are being played on
const REFERRER_POLICY = "strict-origin-when-cross-origin";

// Only injects the iframe once the figure is about to enter the viewport - the player is ~1 MB of third-party JavaScript, which otherwise dominates the whole page's transfer weight for a visitor who never scrolls down to it. `iframe.loading = "lazy"` is not enough on its own: the browser only honours it for an iframe far enough down the page, and the element still belongs to the initial load when it is inserted on connect(). 200px of margin so the player is ready by the time it is actually scrolled into view.
const ROOT_MARGIN = "200px";

// Deliberately not coupled to any particular consent-banner implementation or bundle - reacts to an optional external contract instead: a banner element matching CONSENT_BANNER_SELECTOR present in the page, a `window.CookieConsent` global exposing vanilla-cookieconsent v3's API (https://cookieconsent.orestbida.com/), and its `cc:onConsent`/`cc:onChange` DOM events. c975l/site-bundle's `<twig:c975LSite:General:CookieConsent/>` is one such provider, but any consuming app's own banner satisfying the same contract works just as well - no composer dependency on it either way.
export default class extends Controller {
    static targets = ["placeholder", "consent", "play"];
    static values = { src: String, title: String, width: String, height: String };

    connect() {
        this.onConsent = this.onConsent.bind(this);

        // Nothing to play: an empty src is not "no iframe", the browser resolves it on the document's own URL and the page loads inside itself. Leaving right away also spares a src-less player its consent listeners and its observer, the placeholder staying on screen
        if (!this.srcValue) {
            return;
        }

        // No consent banner on this page - never block content on a site that doesn't use one
        if (!document.querySelector(CONSENT_BANNER_SELECTOR)) {
            this.onConsentSettled();
            return;
        }

        if (window.CookieConsent?.acceptedCategory("content")) {
            this.onConsentSettled();
            return;
        }

        // Consent not yet decided (or lib still loading) - wait for it
        this.listen();
    }

    // "cc:onConsent" fires on every page load once the user's choice is known (not just the first time), so returning visitors who already accepted still get the iframe without needing to click again
    listen() {
        window.addEventListener("cc:onConsent", this.onConsent);
        window.addEventListener("cc:onChange", this.onConsent);
    }

    // Turbo caches the page as it stands, so the revealed play button is put back behind its prompt here rather than left frozen in a snapshot restored before consent is checked again (same reason as flip-card.js)
    disconnect() {
        if (this.hasPlayTarget) {
            this.playTarget.hidden = true;
        }
        if (this.hasConsentTarget) {
            this.consentTarget.hidden = false;
        }

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
            this.onConsentSettled();

            return;
        }

        // The category was withdrawn from the banner after the poster had been revealed - the play button goes back behind the prompt, or it goes on offering a video the visitor has just refused
        if (this.hasPlayTarget) {
            this.playTarget.hidden = true;
            if (this.hasConsentTarget) {
                this.consentTarget.hidden = false;
            }
        }
    }

    // Consent is no longer in the way - what happens next depends on whether there is a poster to hand the decision to the visitor with
    // With one, the player waits for a click: it is ~1 MB of third-party JavaScript, and a grid of six of them would otherwise pull all six as they scroll past. Without one, there is nothing to look at but an empty box, so it loads on approach as it always has
    onConsentSettled() {
        if (!this.hasPlayTarget) {
            this.scheduleIframe();
            return;
        }

        // The listeners stay on while the poster waits for its click, so a withdrawal from the banner takes the button back off screen (see onConsent)
        if (this.hasConsentTarget) {
            this.consentTarget.hidden = true;
        }
        this.playTarget.hidden = false;
    }

    // A withdrawal the banner never announced - its script loaded after the button was revealed, an event missed - leaves this button on screen with no consent behind it, so the click is checked against the current answer rather than against the one that revealed it, and puts the prompt back on screen instead of framing the player
    play() {
        if (window.CookieConsent && !window.CookieConsent.acceptedCategory("content")) {
            this.playTarget.hidden = true;
            if (this.hasConsentTarget) {
                this.consentTarget.hidden = false;
            }
            this.listen();

            return;
        }

        this.renderIframe();
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

        // Only one side is configured in practice, so the other is derived off the iframe's 300x150 default
        const RATIO = 16 / 9;
        let width = this.widthValue ? parseInt(this.widthValue, 10) : null;
        let height = this.heightValue ? parseInt(this.heightValue, 10) : null;
        if (width && !height) {
            height = Math.round(width / RATIO);
        } else if (height && !width) {
            width = Math.round(height * RATIO);
        }

        // An id-scoped <style> element: width/height attributes lose to any CSS declaration, and a CSP nonce never covers an inline style attribute
        if (width && height) {
            if (!this.element.id) {
                this.element.id = `video-iframe-${Math.random().toString(36).slice(2)}`;
            }
            // "auto" + aspect-ratio, not a fixed px height, so a narrow viewport scales it proportionally
            this.sizingStyleEl = createNoncedStyleElement();
            this.sizingStyleEl.textContent = `#${CSS.escape(this.element.id)} iframe { width: ${width}px; height: auto; aspect-ratio: ${width} / ${height}; }`;
        }

        const iframe = document.createElement("iframe");
        iframe.src = this.srcValue;
        iframe.title = this.titleValue || "Video player";
        iframe.frameBorder = "0";
        iframe.allow = ALLOW;
        iframe.referrerPolicy = REFERRER_POLICY;
        iframe.allowFullscreen = true;
        iframe.loading = "lazy";
        this.element.replaceChildren(iframe);
    }
}
