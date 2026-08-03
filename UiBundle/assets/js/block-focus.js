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
        const item = idInput && idInput.closest('.field-collection-item');
        if (!item) return;

        const button = item.querySelector('.accordion-button');
        if (button && button.classList.contains('collapsed')) button.click();

        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
