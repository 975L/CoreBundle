/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// What each age verification provider says in its frame, keyed by its slug (see AgeVerificationProviderInterface::getSlug()): a provider added on the PHP side brings its script here
const DRIVERS = {
    gocam: () => import("./age-gate-gocam.js"),
};

// One frame for the whole page, however many gates it holds: each gate loading its own would fetch the provider and its models as many times. Created by the first gate, removed with the last. "pending" is a click made before the provider was ready, honoured the moment it is; "failed" a driver missing or unloadable; "run" the start under way, dropped by the last gate leaving
const shared = { frame: null, driver: null, starting: null, gates: new Set(), ready: false, pending: false, failed: false, run: null };

// How long a click waits for the provider before the visitor is told it did not answer (a frame refused by the CSP, a network down)
const READY_TIMEOUT = 15000;

// Opens the provider's verification in a frame over the page, and posts the result it hands back to the server, which alone decides (see AgeVerifier). Generic: what the frame says is its driver's business
export default class extends Controller {
    static values = { provider: String, url: String, confirm: String };
    static targets = ["button", "error"];

    connect() {
        shared.gates.add(this);
        shared.starting ??= this.start();
    }

    disconnect() {
        shared.gates.delete(this);
        if (0 === shared.gates.size) {
            window.removeEventListener("message", onMessage);
            shared.frame?.remove();
            Object.assign(shared, { frame: null, driver: null, starting: null, ready: false, pending: false, failed: false, run: null });
        }
    }

    // Loads the provider's driver, then its frame, unseen, so its models are ready by the click
    async start() {
        const load = DRIVERS[this.providerValue];
        if (!load) {
            markFailed();

            return;
        }

        const run = {};
        shared.run = run;
        let driver;
        try {
            driver = (await load()).default;
        } catch {
            markFailed();

            return;
        }

        // Every gate left while the driver loaded: the frame belongs to the next start, if any
        if (run !== shared.run) {
            return;
        }

        shared.driver = driver;
        window.addEventListener("message", onMessage);
        shared.frame = document.createElement("iframe");
        shared.frame.className = "age-gate__frame";
        shared.frame.title = this.buttonTarget.textContent.trim();
        shared.frame.allow = shared.driver.permissions.map((feature) => `${feature} ${shared.driver.origin}`).join("; ");
        shared.frame.addEventListener("load", () => shared.driver.start(post), { once: true });
        shared.frame.src = this.urlValue;
        document.body.appendChild(shared.frame);
    }

    // Clickable from the start: a provider's models may take a few seconds to load, and a visitor clicking before they did is shown the button busy, then the frame as soon as they are
    open() {
        if (shared.ready) {
            expand();

            return;
        }

        if (shared.failed) {
            fail();

            return;
        }

        shared.pending = true;
        setBusy(true);
        setTimeout(() => shared.pending && !shared.ready && fail(), READY_TIMEOUT);
    }
}

// Lays the frame over the page
function expand() {
    shared.pending = false;
    setBusy(false);
    shared.frame?.classList.add("age-gate__frame--open");
}

// No driver to run: a click to come is told at once, one already waiting is told now
function markFailed() {
    shared.failed = true;
    if (shared.pending) {
        fail();
    }
}

// The provider never answered: the buttons released and every gate says so
function fail() {
    shared.pending = false;
    setBusy(false);
    showError();
}

// Every gate's button says the provider is still loading, or no longer
function setBusy(busy) {
    for (const gate of shared.gates) {
        gate.buttonTarget.toggleAttribute("aria-busy", busy);
    }
}

// Sends a message to the frame, and to the provider's origin only
function post(message) {
    shared.frame?.contentWindow?.postMessage(message, shared.driver.origin);
}

// The provider's messages, from its frame and its origin only, read by its driver
function onMessage(event) {
    if (!shared.driver || shared.driver.origin !== event.origin || !shared.frame || event.source !== shared.frame.contentWindow) {
        return;
    }

    const answer = shared.driver.read(event.data, post);
    if ("ready" === answer?.type) {
        shared.ready = true;
        if (shared.pending) {
            expand();
        }
    } else if ("close" === answer?.type) {
        shared.frame.classList.remove("age-gate__frame--open");
    } else if ("result" === answer?.type) {
        confirmResult(answer.payload);
    }
}

// The page is drawn again once the server agreed, the content it now holds being rendered there and nowhere else; refused, every gate says so
async function confirmResult(payload) {
    shared.frame.classList.remove("age-gate__frame--open");
    const [gate] = shared.gates;

    try {
        const response = await fetch(gate.confirmValue, {
            method: "POST",
            headers: { "Content-Type": "application/json", Accept: "application/json" },
            body: JSON.stringify({ payload: payload }),
        });
        if (response.ok) {
            window.location.reload();

            return;
        }
    } catch {
        // Falls through to the message below, a network error reading to the visitor as a refused result
    }

    showError();
}

// Every gate tells the visitor the verification did not go through
function showError() {
    for (const gate of shared.gates) {
        gate.errorTarget.hidden = false;
    }
}
