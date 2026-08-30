/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["face"];

    connect() {
        // The fold itself is the stylesheet's, so the card is painted in shape instead of collapsing into it here - what this adds is the toggles, which turn nothing until this controller is running (see sass/_flip-card.scss and the <noscript> in templates/components/FlipCard/FlipCard.html.twig, which undoes the fold for a browser that never reaches this line)
        this.element.querySelectorAll(".flip-card-toggle").forEach((button) => { button.removeAttribute("hidden"); });
        this.flipped = false;
        this.apply();
    }

    // Turbo caches the page as it stands, so the enhancement is undone here rather than left frozen in a snapshot restored before this controller connects again
    disconnect() {
        this.element.classList.remove("is-flipped");
        this.faceTargets.forEach((face) => { face.removeAttribute("inert"); });
        this.element.querySelectorAll(".flip-card-toggle").forEach((button) => { button.setAttribute("hidden", ""); });
    }

    toggle() {
        this.flipped = !this.flipped;
        this.apply();
        // Focus follows the face that just came up: the one turned away is inert, so a keyboard user standing on its button would otherwise be left with no focus at all, and a screen reader with no clue the card turned. Landing on the revealed face's own toggle also reads its "Voir le recto"/"Voir le verso" label out, which is what says which side is up now
        this.faceTargets[this.flipped ? 1 : 0]?.querySelector(".flip-card-toggle")?.focus();
    }

    // "inert" is what keeps the face turned away out of the tab order and out of the accessibility tree: backface-visibility only hides it from the eye, its buttons and links stay reachable without this
    apply() {
        this.element.classList.toggle("is-flipped", this.flipped);
        this.faceTargets.forEach((face, index) => {
            face.toggleAttribute("inert", index === (this.flipped ? 0 : 1));
        });
    }
}
