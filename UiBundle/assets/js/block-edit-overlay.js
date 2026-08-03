/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// One floating button shared by every editable block: the wrappers are display:contents and can't host it
export default class extends Controller {
    connect() {
        // Several ".blocks" collections can mount this controller, so only the first builds the button
        if (window.blockEditOverlayController) {
            return;
        }
        window.blockEditOverlayController = this;

        this.activeTarget = null;
        this.button = this.buildButton();
        document.body.append(this.button);

        this.onMouseOver = this.onMouseOver.bind(this);
        this.onMouseOut = this.onMouseOut.bind(this);
        this.onFocusIn = this.onFocusIn.bind(this);
        this.onFocusOut = this.onFocusOut.bind(this);
        this.onButtonMouseLeave = this.onButtonMouseLeave.bind(this);
        this.onReposition = this.onReposition.bind(this);

        document.addEventListener("mouseover", this.onMouseOver);
        document.addEventListener("mouseout", this.onMouseOut);
        document.addEventListener("focusin", this.onFocusIn);
        document.addEventListener("focusout", this.onFocusOut);
        this.button.addEventListener("mouseleave", this.onButtonMouseLeave);
        window.addEventListener("scroll", this.onReposition, true);
        window.addEventListener("resize", this.onReposition);
    }

    disconnect() {
        if (window.blockEditOverlayController !== this) {
            return;
        }
        window.blockEditOverlayController = null;

        document.removeEventListener("mouseover", this.onMouseOver);
        document.removeEventListener("mouseout", this.onMouseOut);
        document.removeEventListener("focusin", this.onFocusIn);
        document.removeEventListener("focusout", this.onFocusOut);
        window.removeEventListener("scroll", this.onReposition, true);
        window.removeEventListener("resize", this.onReposition);
        this.button.remove();
    }

    buildButton() {
        const button = document.createElement("a");
        button.className = "block-edit-overlay-btn";
        button.target = "_blank";
        button.rel = "noopener";
        button.tabIndex = -1;
        button.setAttribute("aria-hidden", "true");
        button.innerHTML = '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>';

        const label = document.createElement("span");
        label.textContent = this.element.dataset.editLabel || "Edit";
        button.append(label);

        return button;
    }

    onMouseOver(event) {
        const target = event.target.closest("[data-block-edit-url]");
        if (target) {
            this.show(target);
        }
    }

    onMouseOut(event) {
        const target = event.target.closest("[data-block-edit-url]");
        if (!target || target !== this.activeTarget) {
            return;
        }
        // The button is appended to <body>, not a descendant, so moving onto it must not hide it
        const related = event.relatedTarget;
        if (related && (related === this.button || this.button.contains(related) || target.contains(related))) {
            return;
        }
        this.hide();
    }

    onButtonMouseLeave(event) {
        const related = event.relatedTarget;
        if (this.activeTarget && related && this.activeTarget.contains(related)) {
            return;
        }
        this.hide();
    }

    onFocusIn(event) {
        const target = event.target.closest("[data-block-edit-url]");
        if (target) {
            this.show(target);
        }
    }

    onFocusOut(event) {
        if (!this.activeTarget) {
            return;
        }
        const related = event.relatedTarget;
        if (related && (this.activeTarget.contains(related) || related === this.button)) {
            return;
        }
        this.hide();
    }

    onReposition() {
        if (this.activeTarget) {
            this.position(this.activeTarget);
        }
    }

    show(target) {
        this.activeTarget = target;
        this.button.href = target.dataset.blockEditUrl;
        this.button.removeAttribute("aria-hidden");
        this.button.tabIndex = 0;
        this.position(target);
        this.button.classList.add("is-visible");
    }

    hide() {
        this.activeTarget = null;
        this.button.classList.remove("is-visible");
        this.button.setAttribute("aria-hidden", "true");
        this.button.tabIndex = -1;
    }

    position(target) {
        // The wrapper is display:contents and measures as a zero rect, so its rendered child is measured instead
        const rect = (target.firstElementChild || target).getBoundingClientRect();
        this.button.style.top = `${Math.max(rect.top, 0) + 8}px`;
        // The block's right edge; CSS translateX(-100%) right-aligns the varying label width
        this.button.style.left = `${rect.right - 8}px`;
    }
}
