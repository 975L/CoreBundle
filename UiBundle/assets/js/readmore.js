/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["content", "toggle"];

    connect() {
        // The fold itself is the stylesheet's (see sass/_readmore.scss) - what this adds is telling whether it folded anything, which no selector can ask. The observer fires once on observe, so the first measure is its own
        this.measure = this.measure.bind(this);
        this.observer = new ResizeObserver(this.measure);
        this.observer.observe(this.contentTarget);
        // Web fonts land after that first measure and change how many lines the text takes
        document.fonts?.ready.then(this.measure);
    }

    // Turbo caches the page as it stands, so the class is undone here rather than left frozen in a snapshot restored before this controller connects again
    disconnect() {
        this.observer.disconnect();
        this.element.classList.remove("readmore--complete");
    }

    // Nothing to measure while the text is unfolded - the box then holds all of it, and reading it as "complete" would take the link away with no way back. Folding it again resizes the content, which brings the observer here
    measure() {
        if (this.toggleTarget.checked) {
            return;
        }

        // A pixel of tolerance: the clamped box and its content round apart on a fractional line-height, leaving a link under a text ending exactly on the last folded line
        this.element.classList.toggle("readmore--complete", this.contentTarget.scrollHeight <= this.contentTarget.clientHeight + 1);
    }
}
