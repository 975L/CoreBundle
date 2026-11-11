/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The only origin whose messages are read, and the only one the frame is allowed the camera for
const ORIGIN = "https://go.cam";

// One go.cam frame for the whole page, however many gates it holds: each gate loading its own would fetch go.cam and its models as many times. Created by the first gate, removed with the last
const shared = { frame: null, gates: new Set(), ready: false };

// Opens go.cam's verification in a frame over the page, and posts the signed result it hands back to the server, which alone decides (see AgeVerifier). go.cam's own avsJsSdkV1.js recoded: it ships with no licence, and its permission policy lists full urls where a browser expects origins
export default class extends Controller {
    static values = { url: String, confirm: String };
    static targets = ["button", "error"];

    connect() {
        shared.gates.add(this);
        if (shared.frame?.isConnected) {
            this.buttonTarget.disabled = !shared.ready;

            return;
        }

        window.addEventListener("message", onMessage);
        shared.ready = false;
        shared.frame = document.createElement("iframe");
        shared.frame.className = "age-gate__frame";
        shared.frame.title = this.buttonTarget.textContent.trim();
        shared.frame.allow = ["camera", "microphone", "publickey-credentials-get", "publickey-credentials-create"].map((feature) => `${feature} ${ORIGIN}`).join("; ");
        shared.frame.addEventListener("load", () => emit("avs.statusRequest"), { once: true });
        shared.frame.src = this.urlValue;
        document.body.appendChild(shared.frame);
    }

    disconnect() {
        shared.gates.delete(this);
        if (0 === shared.gates.size) {
            window.removeEventListener("message", onMessage);
            shared.frame?.remove();
            shared.frame = null;
        }
    }

    open() {
        shared.frame?.classList.add("age-gate__frame--open");
    }
}

// Sends one of go.cam's events to its frame, and to that origin only
function emit(name) {
    shared.frame?.contentWindow?.postMessage({ name: name, data: {} }, ORIGIN);
}

// go.cam's events, from its frame only
function onMessage(event) {
    if (ORIGIN !== event.origin || !shared.frame || event.source !== shared.frame.contentWindow) {
        return;
    }

    const name = event.data?.name;
    if ("avs.onInitialVerificationSuccess" === name || "avs.onInitialVerificationNotFound" === name) {
        emit("avs.resourcePreload");
    } else if ("avs.onResourcesLoaded" === name) {
        shared.ready = true;
        for (const gate of shared.gates) {
            gate.buttonTarget.disabled = false;
        }
    } else if ("avs.onCloseIframe" === name) {
        shared.frame.classList.remove("age-gate__frame--open");
    } else if ("avs.onVerificationSuccess" === name) {
        confirmResult(event.data.data?.payload);
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

    for (const other of shared.gates) {
        other.errorTarget.hidden = false;
    }
}
