/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import * as consent from "./consent.js";

import { createNoncedStyleElement } from "./nonced-style-element.js";

// What a platform's player needs to work once it is framed. Not autoplay on purpose: a returning visitor who already accepted gets the iframe injected on scroll, and a src carrying the platform's own autoplay parameter would then start playing at them unasked
const ALLOW = "accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture";

// A nested browsing context takes its initial referrer policy from the framing element, so leaving this off hands the player whatever the embedding page declares - a site sending no referrer at all has its videos refused by the platforms' media servers, which check the domain they are being played on
const REFERRER_POLICY = "strict-origin-when-cross-origin";

// Only injects the iframe once the figure is about to enter the viewport - the player is ~1 MB of third-party JavaScript, which otherwise dominates the whole page's transfer weight for a visitor who never scrolls down to it. `iframe.loading = "lazy"` is not enough on its own: the browser only honours it for an iframe far enough down the page, and the element still belongs to the initial load when it is inserted on connect(). 200px of margin so the player is ready by the time it is actually scrolled into view.
const ROOT_MARGIN = "200px";

// Deliberately not coupled to any particular consent banner: it asks consent.js, whose adapter speaks vanilla-cookieconsent v3 (this bundle's <twig:c975LUi:Cookie:Consent/>) unless the site's own banner registers another, and which keeps the visitor's accord itself on a page with no banner.
export default class extends Controller {
    static targets = ["placeholder", "consent", "play"];
    static values = { src: String, title: String, width: String, height: String };

    connect() {
        this.onConsent = this.onConsent.bind(this);

        // Nothing to play: an empty src is not "no iframe", the browser resolves it on the document's own URL and the page loads inside itself. Leaving right away also spares a src-less player its consent listeners and its observer, the placeholder staying on screen
        if (!this.srcValue) {
            return;
        }

        // Applied on connect rather than when the player is injected: the consent cover stands in for the player, so it has to hold its width - a 320px thumbnail was otherwise announced by a grey box as wide as the page
        this.applySizing();

        // Asked whether or not the page carries a banner: without one, consent.js keeps the accord given in place for the visit
        if (consent.isAccepted("content")) {
            this.onConsentSettled();
            return;
        }

        // Consent not yet decided (or lib still loading) - wait for it
        this.listen();
    }

    // The banner announces the choice on every page load once it is known (not just the first time), so returning visitors who already accepted still get the iframe without needing to click again. Listened once however often asked
    listen() {
        this.unlisten ??= consent.onChange(this.onConsent);
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
        this.unlisten?.();
        this.unlisten = null;
    }

    accept() {
        consent.accept("content");
    }

    onConsent() {
        if (consent.isAccepted("content")) {
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

    // Consent is no longer in the way - what happens next depends on whether there is a poster to hand the decision to the visitor with With one, the player waits for a click: it is ~1 MB of third-party JavaScript, and a grid of six of them would otherwise pull all six as they scroll past. Without one, there is nothing to look at but an empty box, so it loads on approach as it always has
    onConsentSettled() {
        if (!this.hasPlayTarget) {
            this.scheduleIframe();
            return;
        }

        // The listeners go on (or stay on) while the poster waits for its click, so a withdrawal from the banner takes the button back off screen (see onConsent). Attached here rather than only in connect(): a visitor who had already accepted reaches this straight from connect() without ever having listened, and their withdrawal would otherwise be announced to nobody. addEventListener is given the same bound reference every time, so asking twice attaches once
        this.listen();

        if (this.hasConsentTarget) {
            this.consentTarget.hidden = true;
        }
        this.playTarget.hidden = false;
    }

    // A withdrawal the banner never announced - its script loaded after the button was revealed, an event missed - leaves this button on screen with no consent behind it, so the click is checked against the current answer rather than against the one that revealed it, and puts the prompt back on screen instead of framing the player
    play() {
        if (consent.isReady() && !consent.isAccepted("content")) {
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

    // The requested size is carried by the component itself and not by the <iframe> alone: the cover and the player follow each other in the same box, and bounding it once puts both at the same width - a grey box in the video's ratio but as wide as the card shrank at once to the thumbnail as soon as consent was given
    applySizing() {
        // Only one side is configured in practice, so the other is derived off the iframe's 300x150 default
        const RATIO = 16 / 9;
        let width = this.widthValue ? parseInt(this.widthValue, 10) : null;
        let height = this.heightValue ? parseInt(this.heightValue, 10) : null;
        if (width && !height) {
            height = Math.round(width / RATIO);
        } else if (height && !width) {
            width = Math.round(height * RATIO);
        }

        if (!width || !height) {
            return;
        }

        // An id-scoped <style> element: width/height attributes lose to any CSS declaration, and a CSP nonce never covers an inline style attribute
        if (!this.element.id) {
            this.element.id = `video-iframe-${Math.random().toString(36).slice(2)}`;
        }

        // A ceiling and not a fixed width, and "auto" + aspect-ratio rather than a height in pixels: a column narrower than the requested size then scales the whole down proportionally
        const id = CSS.escape(this.element.id);
        this.sizingStyleEl = createNoncedStyleElement();
        this.sizingStyleEl.textContent =
            `#${id} { max-width: ${width}px; margin-inline: auto; }` +
            `#${id} iframe { width: 100%; height: auto; aspect-ratio: ${width} / ${height}; }`;
    }

    renderIframe() {
        this.stopListening();
        this.observer?.disconnect();

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
