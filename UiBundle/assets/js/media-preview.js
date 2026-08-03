/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// EasyAdmin's own Vich image widget shows no live thumbnail for a freshly-picked file - only a text "filename (size)" label - until the page is saved and reloaded. This shows an actual preview in the meantime, for any Vich image field on the page (a manual "Choisir un fichier" click or a file set programmatically, e.g. by block-duplicate.js - both fire a normal "change" event on the input).
document.addEventListener('change', event => {
    const input = event.target;
    if (input.tagName !== 'INPUT' || input.type !== 'file') return;

    // Only image fields render this wrapper class - a non-image Vich field (.ea-vich-file) has no thumbnail to show and is left untouched.
    const wrapper = input.closest('.ea-vich-image');
    if (!wrapper) return;

    const file = input.files && input.files[0];
    let preview = wrapper.querySelector('.ui-media-preview');

    if (!file) {
        if (preview) preview.remove();
        return;
    }

    if (!preview) {
        preview = document.createElement('img');
        preview.className = 'ui-media-preview';
        preview.style.cssText = 'display:block;max-width:200px;max-height:200px;margin-bottom:.5rem;';
        wrapper.prepend(preview);
    }

    // Not URL.createObjectURL(): a "blob:" URI needs "blob:" explicitly allowed in the app's own img-src CSP directive, which isn't a given (and wasn't, in practice) - a "data:" URI instead stays within the "data:" allowance most CSP configs already grant img-src for inline images.
    const reader = new FileReader();
    reader.onload = () => { preview.src = reader.result; };
    reader.readAsDataURL(file);
});
