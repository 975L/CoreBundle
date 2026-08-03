/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Mounted automatically on <body> by controllers-admin.js — no layout override needed. EasyAdmin's "Add" button appends new block rows at the bottom of the collection, often far below the fold, leaving the user to scroll down and hunt for the row they just created. This brings it into view and opens its kind selector right away instead.
export default class extends Controller {
    connect() {
        this.boundOnItemAdded = this.onItemAdded.bind(this);
        document.addEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    disconnect() {
        document.removeEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    onItemAdded(event) {
        const newElement = event.detail && event.detail.newElement;
        if (!newElement) return;

        // block-duplicate.js drives its own freshly-added row (kind + scroll position) itself, so it flags the collection to skip this scroll/focus while it does that.
        const collection = event.detail.collection;
        if (collection && collection.dataset.uiSuppressAutofocus) return;

        // Only for newly added block rows, not for a media entry added inside an existing block
        const kindRow = newElement.querySelector('[data-kind-row]');
        if (!kindRow) return;

        newElement.scrollIntoView({ behavior: 'smooth', block: 'center' });

        // TomSelect wraps the <select> on this same event (EasyAdmin's own listener), so it isn't ready to be focused/opened yet - defer to the next tick.
        const select = kindRow.querySelector('select');
        if (select) {
            setTimeout(() => (select.tomselect || select).focus(), 0);
        }
    }
}
