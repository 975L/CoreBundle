/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// Shared by ea-sortable.js (drag handle) and block-duplicate.js (duplicate button) so both, plus EasyAdmin's own native delete button, always end up grouped in one visual toolbar at the top-right of a row's header - instead of three separate scripts each inserting into the header independently, at the mercy of whichever happens to run first and of the header's own ambient CSS.
const TOOLBAR_CLASS = 'ui-row-toolbar';

// Finds (or creates, on first call for a given row) the toolbar row inside its accordion header. The header's own layout is forced to flex here rather than assumed, so this doesn't depend on EasyAdmin's CSS actually making it one already.
export function getToolbar(item) {
    const header = item.querySelector('.accordion-header');
    if (!header) return null;

    let toolbar = header.querySelector(`:scope > .${TOOLBAR_CLASS}`);
    if (toolbar) return toolbar;

    header.classList.add('ui-row-header-flex');

    toolbar = document.createElement('div');
    toolbar.className = TOOLBAR_CLASS;
    header.appendChild(toolbar);

    // EasyAdmin's own delete button, already wired to its own click handler - moved in (not cloned) so it keeps working, it just visually joins the other row actions instead of wherever it was. EasyAdmin's own CSS still targets it by class once it's here (it was previously positioned via "position: absolute" to place itself in its original header's corner on its own - confirmed by measuring actual rendered button positions, which showed it overlapping the button before it by ~20px instead of leaving the expected gap). Forcing it back to a normal flow position is what lets `order`/`gap` actually govern its position like every other button in this toolbar.
    const deleteButton = header.querySelector('.field-collection-delete-button');
    if (deleteButton) {
        toolbar.appendChild(deleteButton);
    }

    return toolbar;
}

// order: 1 = leftmost (e.g. move handle), 2 = middle (e.g. duplicate), 3 = delete (see getToolbar).
export function addToolbarButton(item, { title, icon, order, onClick }) {
    const toolbar = getToolbar(item);
    if (!toolbar) return null;

    // Classes here are deliberately matched to EasyAdmin's own delete button ("btn btn-link ...", no line-height override) rather than picked independently - two buttons with a different intrinsic height in the same flex row don't visually align even with align-items:center on their container. Padding is overridden the same way on both (see .ui-toolbar-btn in sass/management/_block-collection.scss), rather than left at Bootstrap's default on one and not the other.
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn btn-link ui-toolbar-btn';
    btn.title = title || '';
    // EasyAdmin wraps its own icons (delete, collapse chevron) in <span class="icon">, which its global CSS uses to size/align them consistently - without it, a bare <svg> renders at browser default size/baseline and looks visually out of place next to those.
    // Assigned raw on purpose: "icon" is a literal SVG constant declared by the calling controller (see ea-sortable.js, block-duplicate.js), never a runtime value
    btn.innerHTML = `<span class="icon">${icon || ''}</span>`;
    btn.classList.add(`ui-toolbar-btn--order-${order}`);
    btn.addEventListener('click', onClick);
    toolbar.appendChild(btn);

    return btn;
}
