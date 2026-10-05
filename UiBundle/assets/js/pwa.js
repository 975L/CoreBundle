/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Registers the site's service worker (see PwaController), the browser ignoring a registration already made
export default class extends Controller {
    connect() {
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/sw.js").catch(() => {});
        }
    }
}
