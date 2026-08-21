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
        // Nothing is downloaded at all for a visitor asking for less animation: the library already keeps quiet on its own (disableForReducedMotion below), but it was fetched anyway to do nothing
        if (window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
            return;
        }

        // The controller is imported dynamically (see LAZY_CONTROLLERS in controllers.js), so connect() mostly runs after DOMContentLoaded: subscribing to it without reading readyState never fired again
        if ("loading" === document.readyState) {
            document.addEventListener("DOMContentLoaded", this.launch.bind(this), { once: true });

            return;
        }

        this.launch();
    }

    launch() {
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