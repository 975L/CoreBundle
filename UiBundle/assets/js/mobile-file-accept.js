/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Chrome on Android hands an <input type="file"> whose "accept" list holds nothing but image/video types to the system photo picker, which shows the phone's own gallery and nothing else - no "Files", no Drive, no third-party storage provider (kDrive, Nextcloud...). Dropping the attribute sends that same input to the document picker instead, where every provider installed on the phone appears. Nothing is lost by doing so: "accept" is a client-side hint anyone can bypass, and the block kind's media types are enforced server-side (see MediaUploadType).
// Delegated on the document rather than set on each input: block media rows come from a collection prototype and whole block forms arrive over AJAX (see BlockFormController), so most of the inputs this applies to do not exist yet when this module runs. "pointerdown" fires before the click that opens the picker, and only a non-mouse pointer is acted on - a desktop admin keeps their images-only file dialog.

// A list mixing in any other type (application/pdf, font/woff2...) already opens the document picker, and its filter is worth keeping
const MEDIA_ONLY = /^(image|video)\//;

document.addEventListener('pointerdown', event => {
    if ('mouse' === event.pointerType) return;

    // A tap can land on the input itself or on the label that opens it - HTMLLabelElement.control resolves both the "for" attribute and the wrapping form
    const target = event.target.closest?.('input[type="file"][accept], label');
    const input = target?.control ?? target;
    if (!input || 'file' !== input.type || !input.hasAttribute('accept')) return;

    const types = input.getAttribute('accept').split(',').map(type => type.trim()).filter(Boolean);
    if (!types.every(type => MEDIA_ONLY.test(type))) return;

    input.removeAttribute('accept');
});
