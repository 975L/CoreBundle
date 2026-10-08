/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { download, isSupported, readRegistry } from "./pwa-downloads-store.js";

// The "Download for offline use" button a page writes (see templates/components/Pwa/Download.html.twig): keeps the page itself, the files it names and those it has already loaded, so it opens again with no network
export default class extends Controller {
    static targets = ["button", "progress", "done", "error"];
    static values = { key: String, title: String, image: String, urls: Array };

    async connect() {
        if (!isSupported()) {
            return;
        }

        this.element.hidden = false;
        this.show((await readRegistry())[this.keyValue] ? "done" : "button");
    }

    async download() {
        this.show("progress");
        this.progressTarget.value = 0;

        try {
            await download(this.keyValue, { title: this.titleValue, url: this.pageUrl(), image: this.imageValue, urls: this.urls() }, (done, total) => {
                this.progressTarget.value = Math.round((done / total) * 100);
            });
            this.show("done");
        } catch {
            this.show("error");
            this.buttonTarget.hidden = false;
        }
    }

    // One state shown at a time: the button, the bar, the confirmation or the failure
    show(state) {
        ["button", "progress", "done", "error"].forEach((name) => {
            this[`${name}Target`].hidden = name !== state;
        });
    }

    // The page as its address bar names it, without the fragment the browser never sends
    pageUrl() {
        return window.location.href.split("#")[0];
    }

    // The page, the files it names, every module of its importmap - lazy ones included, a controller may load only once offline - and its styles and images, all of this site: those are what draws it again offline, the files of a deploy being dropped from the worker's own cache by the next one. Read from the document rather than from the browser's resource timings, which under Turbo pile up every page visited since the first
    urls() {
        const origin = window.location.origin;
        const importmap = document.querySelector('script[type="importmap"]');
        const modules = importmap ? Object.values(JSON.parse(importmap.textContent).imports || {}) : [];
        const files = [...document.querySelectorAll('link[rel="stylesheet"][href], link[rel="modulepreload"][href], img[src]')].map((element) => element.href || element.src);
        const urls = [this.pageUrl(), ...[...this.urlsValue, ...modules].map((url) => new URL(url, origin).href), ...files];

        return [...new Set(urls.filter((url) => url.startsWith(origin + "/") && !url.endsWith("/sw.js")))];
    }
}
