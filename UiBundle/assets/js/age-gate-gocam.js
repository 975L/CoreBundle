/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// go.cam's side of the age gate (see assets/js/age-gate.js and AgeVerification/GoCamProvider): its own avsJsSdkV1.js recoded, which ships with no licence and lists full urls in its permission policy where a browser expects origins
export default {
    origin: "https://go.cam",

    // What its frame is allowed: the camera for the selfie, a passkey to remember the visitor
    permissions: ["camera", "microphone", "publickey-credentials-get", "publickey-credentials-create"],

    // Asks the frame where the visitor stands, once it has loaded
    start(post) {
        post({ name: "avs.statusRequest", data: {} });
    },

    // One of go.cam's events, read into what the gate does with it: preloading answered here, the rest handed back
    read(message, post) {
        const name = message?.name;
        if ("avs.onInitialVerificationSuccess" === name || "avs.onInitialVerificationNotFound" === name) {
            post({ name: "avs.resourcePreload", data: {} });

            return null;
        }

        if ("avs.onResourcesLoaded" === name) {
            return { type: "ready" };
        }

        if ("avs.onCloseIframe" === name) {
            return { type: "close" };
        }

        if ("avs.onVerificationSuccess" === name) {
            return { type: "result", payload: message.data?.payload };
        }

        return null;
    },
};
