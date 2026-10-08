/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The browser's install offer, fired once per page load: kept at the module's level so the button comes back after a Turbo visit, which swaps the body and reconnects the controller without firing it again
let installPrompt = null;
const installButtons = new Set();

// Shows or hides every install button on the page, whichever controller instance wrote it
function refreshInstallButtons() {
    installButtons.forEach((button) => {
        button.hidden = null === installPrompt;
    });
}

window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    installPrompt = event;
    refreshInstallButtons();
});

window.addEventListener("appinstalled", () => {
    installPrompt = null;
    refreshInstallButtons();
});

// Registers the site's service worker (see PwaController), the browser ignoring a registration already made, and drives the install button a template may write as an "install" target, hidden until the browser offers to install
export default class extends Controller {
    static targets = ["install"];

    connect() {
        if ("serviceWorker" in navigator) {
            navigator.serviceWorker.register("/sw.js").catch(() => {});
        }
    }

    installTargetConnected(button) {
        installButtons.add(button);
        button.hidden = null === installPrompt;
    }

    installTargetDisconnected(button) {
        installButtons.delete(button);
    }

    // Opens the browser's own install dialog. The offer can only be used once, whatever the answer: the browser fires a new one later if the visitor declined
    async install() {
        if (null === installPrompt) {
            return;
        }

        const prompt = installPrompt;
        installPrompt = null;
        refreshInstallButtons();
        await prompt.prompt();
    }
}
