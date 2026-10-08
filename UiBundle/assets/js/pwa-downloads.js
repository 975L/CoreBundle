/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { isSupported, readRegistry, remove, removeAll } from "./pwa-downloads-store.js";

// The "My downloads" page (see templates/pwa/downloads.html.twig): what is kept for offline use, the room it takes, and a way to drop one download or all of them
export default class extends Controller {
    static targets = ["list", "item", "total", "empty", "removeAll", "unsupported"];

    connect() {
        if (!isSupported()) {
            this.unsupportedTarget.hidden = false;
            return;
        }

        this.render();
    }

    // Each download cloned from the template the page writes, so every text comes translated from the server
    async render() {
        const entries = Object.entries(await readRegistry());
        this.listTarget.replaceChildren(...entries.map(([key, entry]) => this.item(key, entry)));

        const total = entries.reduce((sum, [, entry]) => sum + (entry.size || 0), 0);
        this.totalTarget.textContent = this.formatSize(total);
        this.totalTarget.parentElement.hidden = 0 === entries.length;
        this.emptyTarget.hidden = 0 !== entries.length;
        this.removeAllTarget.hidden = 0 === entries.length;
    }

    item(key, entry) {
        const item = this.itemTarget.content.firstElementChild.cloneNode(true);
        const link = item.querySelector("a");
        link.href = entry.url;
        link.querySelector("[data-title]").textContent = entry.title;
        const image = item.querySelector("img");
        if (entry.image) {
            image.src = entry.image;
        } else {
            image.remove();
        }
        item.querySelector("[data-size]").textContent = this.formatSize(entry.size || 0);
        item.querySelector("button").dataset.key = key;

        return item;
    }

    async remove(event) {
        await remove(event.currentTarget.dataset.key);
        this.render();
    }

    async removeAll() {
        await removeAll();
        this.render();
    }

    // Megabytes in the page's language, one decimal
    formatSize(bytes) {
        return new Intl.NumberFormat(document.documentElement.lang || undefined, { style: "unit", unit: "megabyte", maximumFractionDigits: 1 }).format(bytes / 1048576);
    }
}
