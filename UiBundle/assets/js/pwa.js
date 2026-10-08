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

// iOS offers no install prompt, Safari installing from its Share menu: there the button explains how, until the site is opened as the installed app. An iPad says it is a Mac, its touch screen telling them apart
const isIos = /iPhone|iPad|iPod/.test(navigator.userAgent) || ("MacIntel" === navigator.platform && navigator.maxTouchPoints > 1);
const isStandalone = window.matchMedia("(display-mode: standalone)").matches || true === navigator.standalone;
const explainsIosInstall = isIos && !isStandalone;

// Whether the button has something to do: an offer from the browser to use, or the iOS way to explain
function canInstall() {
    return null !== installPrompt || explainsIosInstall;
}

// Shows or hides every install button on the page, whichever controller instance wrote it
function refreshInstallButtons() {
    installButtons.forEach((button) => {
        button.hidden = !canInstall();
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
        button.hidden = !canInstall();
    }

    installTargetDisconnected(button) {
        installButtons.delete(button);
    }

    // Opens the browser's own install dialog, or on iOS the explanation the layout writes (see layout.html.twig). The offer can only be used once, whatever the answer: the browser fires a new one later if the visitor declined
    async install() {
        if (null === installPrompt) {
            if (explainsIosInstall) {
                document.getElementById("pwa-ios-install")?.showModal();
            }

            return;
        }

        const prompt = installPrompt;
        installPrompt = null;
        refreshInstallButtons();
        await prompt.prompt();
    }
}
