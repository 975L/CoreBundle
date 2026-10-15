/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Opens the high resolution over the page instead of on a page of its own: a listing stays on the stored (medium) files, and the heavy one is only ever fetched for the picture the visitor actually asks to see
export default class extends Controller {
    static targets = ["dialog"];

    open(event) {
        // The click a browser may still send after a swipe or a long press: the visitor was browsing slides or holding the picture, not asking to see it
        if (Date.now() < (this.ignoreClickUntil ?? 0)) {
            event.preventDefault();
            return;
        }

        // A modified click asks for a new tab or a new window: the browser keeps the link, and the lightbox stays out of the way
        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        // <dialog> closes an open <p> implicitly, so a component placed in one has its dialog hoisted out of the controller's element: the link is left to do what it already promises rather than dying on a missing target
        if (!this.hasDialogTarget) {
            return;
        }

        // The link is a real one, pointing at the file itself, so it still opens the high resolution when this script doesn't run
        event.preventDefault();

        // Built on the first opening only, the browser cache serving the next ones: an <img> written without a src would be invalid HTML, and an empty one would have the browser re-request the page itself
        if (!this.dialogTarget.querySelector(".image-zoom__image")) {
            const image = document.createElement("img");
            image.className = "image-zoom__image";
            const shown = event.currentTarget.querySelector("img");
            image.alt = shown?.alt ?? "";

            // A media uploaded before its entity produced a "-highres.webp" has no such file: the picture already on the page is opened instead of a broken image
            image.addEventListener("error", () => {
                if (shown && image.src !== shown.src) {
                    image.src = shown.src;
                    // The fallback failing as well: the image is dropped, so the next opening builds it again rather than showing a broken one each time
                    image.addEventListener("error", () => image.remove(), { once: true });
                    return;
                }
                image.remove();
            }, { once: true });
            image.src = event.currentTarget.href;
            this.dialogTarget.append(image);
        }

        this.dialogTarget.showModal();
    }

    // Where and when a touch on the link began, so its end can tell a tap from a swipe or a long press
    touchStart(event) {
        const touch = event.touches[0];
        this.touchStarted = { x: touch.clientX, y: touch.clientY, at: Date.now() };
    }

    // A touch that travelled (a swipe through a slider) or lasted (a long press, which pauses one) is not a tap, so the click following it is ignored
    touchEnd(event) {
        const touch = event.changedTouches[0];
        if (!this.touchStarted || !touch) {
            return;
        }

        const moved = Math.abs(touch.clientX - this.touchStarted.x) > 10 || Math.abs(touch.clientY - this.touchStarted.y) > 10;
        const held = Date.now() - this.touchStarted.at > 500;
        this.ignoreClickUntil = moved || held ? Date.now() + 500 : 0;
        this.touchStarted = null;
    }

    // Anything clicked inside closes - the image and the backdrop alike, which is why there is no close button. Escape is handled by the dialog itself
    close() {
        this.dialogTarget.close();
    }
}
