/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Opens the site search dialog (components/AiSearch/Dialog.html.twig) from anything marked data-ai-search-open, or on Ctrl/Cmd+K - listening on the document, the magnifier sitting in a navbar far from the dialog's own element
export default class extends Controller {
    openFrom(event) {
        const trigger = event.target.closest("[data-ai-search-open]");
        if (null !== trigger) {
            event.preventDefault();
            this.open();
        }
    }

    // The shortcut every documentation search answers, left alone while the dialog is already open - Chrome's autofill fires a plain Event named "keydown" that Stimulus lets through its key filter, hence the KeyboardEvent check
    shortcut(event) {
        if (!(event instanceof KeyboardEvent)) {
            return;
        }
        event.preventDefault();
        if (!this.element.open) {
            this.open();
        }
    }

    open() {
        this.element.showModal();
        this.element.querySelector("input")?.focus();
    }

    // A click landing on the dialog itself and not on its content is a click on the backdrop around it
    backdrop(event) {
        if (event.target === this.element) {
            this.element.close();
        }
    }
}
