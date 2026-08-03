/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Fetches the kind-specific "data" sub-form and injects it next to a block's kind <select>. Shared by the normal kind-change flow below and by block-duplicate.js, which posts the source block's current field values so the injected sub-form comes back pre-filled instead of empty.
export function loadBlockData(selectElement, kindUrl, kind, body) {
    const prefix   = selectElement.name.replace(/\[kind\]$/, '');
    const idPrefix = selectElement.id.replace(/_kind$/, '');
    const kindRow  = selectElement.closest('[data-kind-row]');
    const compound = kindRow && kindRow.parentElement;
    if (!compound) return Promise.resolve();

    let container = compound.querySelector('.block-data-form');
    if (!container) {
        container = document.createElement('div');
        container.className = 'block-data-form';
        // Inserted before "animation" (rather than appended at the end) so the kind-specific fields (e.g. MenuLinkType's "target") always render above it, matching the server-rendered order
        const animationRow = compound.querySelector('[data-animation-row]');
        compound.insertBefore(container, animationRow);
    }

    if (!kind) {
        container.innerHTML = '';
        return Promise.resolve();
    }

    const init = body ? { method: 'POST', body } : {};

    return fetch(kindUrl + '?k=' + encodeURIComponent(kind), init)
        .then(r => r.text())
        .then(html => {
            container.innerHTML = html
                .replaceAll('_block_[', prefix + '[')
                // Scoped to attribute-value/anchor starts (id="block_…", for="block_…", href="#block_…") rather than a blind replace, so it can't corrupt an unrelated class or text containing "block_".
                .replace(/(["'#])block_/g, `$1${idPrefix}_`);

            // The outer <form>'s enctype is fixed by Symfony at initial render, based on which fields existed server-side at that time. A newly picked kind can inject a file input here that didn't exist yet then, so the form would still submit as urlencoded and silently drop it.
            if (container.querySelector('input[type="file"]')) {
                const form = selectElement.closest('form');
                if (form) form.enctype = 'multipart/form-data';
            }

            document.dispatchEvent(new CustomEvent('c975l:block-data-loaded'));
            // Wires EasyAdmin's "add" button on the injected collection; "newElement" must be a real element, field-slug.js dereferencing it unconditionally
            document.dispatchEvent(new CustomEvent('ea.collection.item-added', { detail: { newElement: container } }));
        });
}

export default class extends Controller {
    static values = { kindUrl: String };

    loadData(event) {
        loadBlockData(this.element, this.kindUrlValue, event.target.value);
    }
}
