/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        // Points at the copy vendored in this bundle's public/ - self-hosted rather than pulled from a CDN, which would hand a third party every visitor's IP and force an external script-src host into the site's CSP
        script: { type: String, default: "/bundles/c975lui/js/confetti.browser.min.js" },
    };

    connect() {
        document.addEventListener("DOMContentLoaded", this.onDomContentLoaded.bind(this));
    }

    onDomContentLoaded() {
        // https://github.com/catdad/canvas-confetti (ISC)
        this.loadScript(this.scriptValue, () => {
            confetti({ particleCount: 500, disableForReducedMotion: true });
        });
    }

    loadScript(src, callback) {
        const script = document.createElement("script");
        script.src = src;
        script.onload = callback;
        document.head.appendChild(script);
    }
}