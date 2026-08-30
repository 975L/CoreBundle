/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { addToolbarButton } from "./block-toolbar.js";

// No width/height here, deliberately - see the same note in ea-sortable.js and block-duplicate.js: EasyAdmin's own icons rely entirely on its global ".icon svg" CSS for sizing, so these need to too, to stay consistent.
const ICON_EYE = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">'
    + '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/>'
    + '</svg>';

const ICON_EYE_OFF = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">'
    + '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>'
    + '<line x1="1" y1="1" x2="23" y2="23"/>'
    + '</svg>';

// Mounted automatically on <body> by controllers-admin.js — no layout override needed. Adds a "hide" toggle to each block row: a hidden block keeps its fields, its medias and its place in the page, and simply renders nowhere on the front (see BlockExtension::renderBlock) - which is what lets a layout be tried without a block instead of deleting it and building it back afterwards.
// Nothing is written on click: the state rides the row's own "hidden" checkbox (see BlockType), so it is stored by the page's save like every other field, and a form that is never saved changes nothing.
export default class extends Controller {
    connect() {
        this.element.querySelectorAll('[data-ea-collection-field]').forEach(field => { this.initField(field); });

        this.boundOnItemAdded = this.onItemAdded.bind(this);
        document.addEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    disconnect() {
        document.removeEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    onItemAdded(event) {
        const newElement = event.detail?.newElement;
        if (newElement) this.addButtonFor(newElement);
    }

    initField(field) {
        this.collectionItems(field).forEach(item => { this.addButtonFor(item); });
    }

    collectionItems(field) {
        return [...field.querySelectorAll('.field-collection-item')]
            .filter(item => item.closest('[data-ea-collection-field]') === field);
    }

    // Only a block row gets the toggle - a media row or a nested collection item (a card, a step...) has no "hidden" of its own to carry. This controller is mounted on <body>, so it sees every collection in the whole admin and leaves everything else alone.
    addButtonFor(item) {
        if (item.dataset.uiHideBtn || !item.querySelector('[data-kind-row]')) return;

        const checkbox = this.ownCheckbox(item);
        if (!checkbox) return;

        item.dataset.uiHideBtn = '1';

        const button = addToolbarButton(item, {
            title: '',
            icon: '',
            order: 3,
            onClick: () => {
                checkbox.checked = !checkbox.checked;
                this.applyState(item, checkbox, button);
            },
        });

        // Read from the checkbox rather than assumed false: a row rendered for a block already hidden has to come up dimmed on load, not only once it is clicked
        if (button) this.applyState(item, checkbox, button);
    }

    // A container's slots are block rows too, each with a "hidden" checkbox of its own nested inside this one's markup - hence the ownership test, the same one collectionItems() makes.
    ownCheckbox(item) {
        return [...item.querySelectorAll('.ui-block-hidden')]
            .find(input => input.closest('.field-collection-item') === item);
    }

    applyState(item, checkbox, button) {
        const hidden = checkbox.checked;

        item.classList.toggle('ui-row-hidden', hidden);
        button.title = hidden ? 'Afficher' : 'Cacher';
        // Assigned raw on purpose, as in block-toolbar.js: both icons are literal SVG constants declared above, never a runtime value
        button.querySelector('.icon').innerHTML = hidden ? ICON_EYE_OFF : ICON_EYE;
    }
}
