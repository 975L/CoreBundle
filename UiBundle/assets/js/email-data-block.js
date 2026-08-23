/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Mounted automatically on <body> by controllers-admin.js — no layout override needed. A saved data block (the order's lines, a form's submitted fields) is what its email is for, so EmailTemplateCrudController puts it back when a submission drops it. This takes away the button that would do the dropping: a control that appears to work and is undone on save reads as a bug. Moving the block is untouched — it is deletion alone that is refused.
export default class extends Controller {
    connect() {
        this.hideDeleteButtons();

        // EasyAdmin renumbers and redraws the collection on add/remove, so what was hidden comes back
        this.boundHide = () => this.hideDeleteButtons();
        document.addEventListener('ea.collection.item-added', this.boundHide);
    }

    disconnect() {
        document.removeEventListener('ea.collection.item-added', this.boundHide);
    }

    hideDeleteButtons() {
        this.element.querySelectorAll('[data-ea-collection-field] .field-collection-item').forEach((item) => {
            const id = item.querySelector('input[type="hidden"][id$="_id"]');
            const type = item.querySelector('select[id$="_type"]');

            // Saved (it has an id) and of a kind the code fills in - a new row is left alone, the admin may still be choosing what it is
            if (!id || '' === id.value || !type || !['slot', 'fields_table'].includes(type.value)) return;

            item.querySelectorAll('.field-collection-item-action-delete, [data-action*="delete"]').forEach((button) => {
                button.remove();
            });
        });
    }
}
