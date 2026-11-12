/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Google reCAPTCHA v3's side of the captcha (see assets/js/captcha.js and Captcha/RecaptchaProvider): its api.js weighs ~765 KB and ~1.5 s of main thread, which is why the controller only asks for it on the first interaction with the form
export default {
    // Injects api.js, resolving once grecaptcha is usable
    load(siteKey) {
        return new Promise((resolve, reject) => {
            const script = document.createElement("script");
            script.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
            script.async = true;
            script.defer = true;
            script.addEventListener("load", () => (window.grecaptcha ? window.grecaptcha.ready(resolve) : reject()));
            script.addEventListener("error", reject);
            document.head.appendChild(script);
        });
    },

    // A fresh token for this submission, the action letting Google's console break scores down per form
    token(siteKey, action) {
        return window.grecaptcha.execute(siteKey, { action });
    },
};
