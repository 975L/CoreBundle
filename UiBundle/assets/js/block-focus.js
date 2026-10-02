/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Mounted automatically on <body> by controllers-admin.js — no layout override needed. The Media library's "used in" links point here with a "focusBlock=<id>" query param (see SiteMediaUsageProvider) - opens that block's accordion row and scrolls to it, instead of leaving the user to hunt through every block on the page for the right one.
export default class extends Controller {
    connect() {
        const blockId = new URLSearchParams(window.location.search).get('focusBlock');
        if (!blockId) return;

        // Each block row carries its own unmapped, hidden "id" field (see BlockType) - excluding "[medias]" keeps this from matching a media's own "id" field nested inside a block instead.
        const idInput = [...this.element.querySelectorAll('input[name$="[id]"]')]
            .find(el => el.value === blockId && !el.name.includes('[medias]'));
        const item = idInput?.closest('.field-collection-item');
        if (!item) return;

        const button = item.querySelector('.accordion-button');
        if (button?.classList.contains('collapsed')) button.click();

        // An item of the block named by a "focusItem" query param - "rows.3", what the front's per-item pencil sends (see edit-pencils.js) - is opened and scrolled to in place of the whole block
        const entry = this.entry(item, new URLSearchParams(window.location.search).get('focusItem'));
        if (!entry) {
            item.scrollIntoView({ behavior: 'smooth', block: 'center' });

            return;
        }

        const entryButton = entry.querySelector('.accordion-button');
        if (entryButton?.classList.contains('collapsed')) entryButton.click();

        // Once both accordions are open (Bootstrap's collapse takes 350ms): until then the entry has no final place to scroll to
        setTimeout(() => {
            entry.scrollIntoView({ behavior: 'smooth', block: 'center' });
            entry.querySelector('input:not([type="hidden"]), textarea, select')?.focus({ preventScroll: true });
        }, 400);
    }

    // "rows.3" read as the "[rows][3][" its fields are named with, whatever form name comes before it
    entry(item, path) {
        if (!path) return null;

        const name = `${path.split('.').map(part => `[${part}]`).join('')}[`;

        return item.querySelector(`[name*="${CSS.escape(name)}"]`)?.closest('.field-collection-item') ?? null;
    }
}
