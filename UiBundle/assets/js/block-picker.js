/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import Handlers from "./handlers.js";

// Puts a visual palette in front of a block's kind <select>: a grid of silhouettes and labels, opened as a full-height sheet on a phone and as a centred dialog from a tablet up, so a page can be composed from the device it will mostly be read on. Not a Stimulus controller, for the same reason icon-picker.js isn't one: block rows are cloned into the page by EasyAdmin's collection script long after load, and a plain module with delegated listeners covers those without anything having to mount it again. Styling lives in sass/management/_block-picker.scss - a CSP with a nonce on style-src blocks any <style> this could create.
// The <select> itself is never removed, only hidden by CSS once the trigger is in place (see .ui-block-picker-on): every kind-change rule of BlockType still reads a posted "kind", and with no JavaScript the select is simply still there.

// The five parts every silhouette is drawn from; which ones show, and how they sit, is decided per kind in the stylesheet
const THUMB_PARTS = ['media', 'title', 'line', 'line', 'action'];

let dialog = null;
let openFor = null;

// The palette's own wording, which no Twig template can reach: the markup below is built in the browser
function translate(key) {
    return Handlers.translate(key);
}

function thumb(kind) {
    const frame = document.createElement('span');
    frame.className = `ui-block-thumb ui-block-thumb--${kind}`;
    frame.setAttribute('aria-hidden', 'true');

    for (const part of THUMB_PARTS) {
        const piece = document.createElement('b');
        piece.className = `ui-block-thumb__${part}`;
        frame.appendChild(piece);
    }

    return frame;
}

// The label a chosen kind shows on its trigger, and the one the placeholder option carries while nothing is chosen
function triggerLabel(select) {
    const chosen = select.options[select.selectedIndex];
    if (!chosen || !chosen.value) return '';

    return chosen.dataset.label || chosen.textContent.trim();
}

function refreshTrigger(row) {
    const select = row.querySelector('select');
    const trigger = row.querySelector('.ui-block-picker-trigger');
    if (!select || !trigger) return;

    const label = triggerLabel(select);
    const text = trigger.querySelector('.ui-block-picker-trigger__label');
    const previous = trigger.querySelector('.ui-block-thumb');

    // The placeholder option is already translated by Symfony, so the empty state costs no string of its own
    text.textContent = label || select.options[0]?.textContent.trim() || '';
    trigger.classList.toggle('ui-block-picker-trigger--empty', !label);

    if (previous) previous.remove();
    if (select.value) trigger.insertBefore(thumb(select.value), text);
}

function buildTrigger() {
    const trigger = document.createElement('button');
    // Explicit, or the browser would take it for the form's submit button and post the whole block form on the first tap
    trigger.type = 'button';
    trigger.className = 'ui-block-picker-trigger';

    const text = document.createElement('span');
    text.className = 'ui-block-picker-trigger__label';
    trigger.appendChild(text);

    const caret = document.createElement('span');
    caret.className = 'ui-block-picker-trigger__caret';
    caret.setAttribute('aria-hidden', 'true');
    caret.textContent = '▾';
    trigger.appendChild(caret);

    return trigger;
}

// Names the trigger after the row's own field label, so it is announced as the field it replaces rather than as a bare button
function labelTrigger(row, trigger) {
    const label = row.querySelector('label');
    if (!label) return;

    if (!label.id) {
        label.id = `ui-block-kind-label-${Math.random().toString(36).slice(2, 9)}`;
    }
    trigger.setAttribute('aria-labelledby', label.id);
}

function enhance(row) {
    if (row.dataset.uiBlockPicker) return;

    const select = row.querySelector('select');
    if (!select) return;

    row.dataset.uiBlockPicker = 'on';
    const trigger = buildTrigger();
    labelTrigger(row, trigger);
    // Appended to the row itself, never next to the select: EasyAdmin's autocomplete moves the select inside a TomSelect wrapper of its own that the stylesheet hides along with it - a trigger placed there would be hidden with them
    row.appendChild(trigger);
    // Added last: until the trigger is in the page, hiding the select would leave the row with no control at all
    row.classList.add('ui-block-picker-on');
    refreshTrigger(row);
}

function enhanceAll(scope) {
    (scope || document).querySelectorAll('[data-kind-row]').forEach(enhance);
}

function refreshTriggersIn(scope) {
    scope.querySelectorAll('[data-kind-row]').forEach(refreshTrigger);
}

function buildDialog() {
    const element = document.createElement('dialog');
    element.className = 'ui-block-picker';

    const head = document.createElement('div');
    head.className = 'ui-block-picker__head';

    // Deliberately not inside a <form method="dialog">: pressing Enter to validate a search would submit that form and close the sheet on the editor
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'ui-block-picker__search';
    search.placeholder = translate('block.picker.search');
    search.setAttribute('aria-label', translate('block.picker.search'));
    head.appendChild(search);

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'ui-block-picker__close';
    close.setAttribute('aria-label', translate('block.picker.close'));
    close.textContent = '✕';
    head.appendChild(close);

    const body = document.createElement('div');
    body.className = 'ui-block-picker__body';

    element.appendChild(head);
    element.appendChild(body);
    // Appended to <body>, outside the EasyAdmin form: a <dialog> left inside it would nest its own controls in a form that is about to be posted
    document.body.appendChild(element);

    close.addEventListener('click', () => element.close());
    search.addEventListener('input', () => filter(element, search.value));
    // A native dialog does not close on its backdrop; without this the sheet can only be dismissed by the button or Escape, which on a phone leaves nowhere obvious to tap
    element.addEventListener('click', event => {
        if (event.target === element) element.close();
    });
    element.addEventListener('close', () => { openFor = null; });

    return element;
}

// Built from the clicked row's own <select> rather than from a list of its own: which kinds a row may take depends on its context (a menu, a column of a flexible section...), and a kind the context no longer offers is still put back by BlockType for the row already holding it
function fill(element, select) {
    const body = element.querySelector('.ui-block-picker__body');
    body.innerHTML = '';

    // A select whose choices were never grouped - a context down to a single category - still fills one nameless group rather than an empty sheet
    const groups = select.querySelectorAll('optgroup');
    for (const group of groups.length ? groups : [select]) {
        const heading = document.createElement('p');
        heading.className = 'ui-block-picker__category';
        heading.textContent = group.label || '';
        body.appendChild(heading);

        const grid = document.createElement('div');
        grid.className = 'ui-block-picker__grid';

        for (const option of group.querySelectorAll('option')) {
            if (!option.value) continue;
            grid.appendChild(kindButton(option, select.value));
        }

        body.appendChild(grid);
    }

    const empty = document.createElement('p');
    empty.className = 'ui-block-picker__empty';
    empty.textContent = translate('block.picker.empty');
    empty.hidden = true;
    body.appendChild(empty);
}

function kindButton(option, current) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'ui-block-kind';
    button.dataset.kind = option.value;
    button.setAttribute('aria-pressed', String(option.value === current));

    const label = document.createElement('span');
    label.className = 'ui-block-kind__label';
    // The option's own text is the label and the description run together, which the palette shows on two lines instead
    label.textContent = option.dataset.label || option.textContent.trim();

    button.appendChild(thumb(option.value));
    button.appendChild(label);

    // Written on the options by BlockType's choice_attr, so what an editor reads here is the very description the registry holds for that kind
    const description = option.dataset.description;
    if (description) {
        const help = document.createElement('span');
        help.className = 'ui-block-kind__description';
        help.textContent = description;
        button.appendChild(help);
    }

    return button;
}

function filter(element, query) {
    const needle = query.trim().toLowerCase();
    let shown = 0;

    for (const grid of element.querySelectorAll('.ui-block-picker__grid')) {
        let visible = 0;

        for (const button of grid.querySelectorAll('.ui-block-kind')) {
            // The kind's own slug is searched along with its label and description: it is what the editor sees in the block list and in the URL of a shared edit link
            const haystack = `${button.textContent} ${button.dataset.kind}`.toLowerCase();
            const matches = !needle || haystack.includes(needle);
            button.hidden = !matches;
            if (matches) visible += 1;
        }

        grid.hidden = 0 === visible;
        grid.previousElementSibling.hidden = 0 === visible;
        shown += visible;
    }

    element.querySelector('.ui-block-picker__empty').hidden = 0 !== shown;
}

function open(row) {
    const select = row.querySelector('select');
    if (!select) return;

    dialog = dialog || buildDialog();
    openFor = row;

    const search = dialog.querySelector('.ui-block-picker__search');
    search.value = '';
    fill(dialog, select);
    filter(dialog, '');
    dialog.showModal();
    dialog.querySelector('.ui-block-picker__body').scrollTop = 0;
}

function choose(kind) {
    const row = openFor;
    const select = row?.querySelector('select');
    if (!select) return;

    // EasyAdmin wraps this select with TomSelect: ChoiceAutocompleteExtension writes data-ea-widget="ea-autocomplete" on any list of ten choices or more, which the kinds are well past. TomSelect then keeps its own copy of the value that writing select.value behind its back would contradict. Told to stay silent it syncs itself without firing, so the one change event block.js needs is always the one dispatched here: letting TomSelect fire its own on top would load the kind's sub-form twice.
    if (select.tomselect) {
        select.tomselect.setValue(kind, true);
    }
    select.value = kind;
    select.dispatchEvent(new Event('change', { bubbles: true }));

    refreshTrigger(row);
    dialog.close();
}

document.addEventListener('click', event => {
    const trigger = event.target.closest('.ui-block-picker-trigger');
    if (trigger) {
        open(trigger.closest('[data-kind-row]'));
        return;
    }

    const kind = event.target.closest('.ui-block-kind');
    if (kind) choose(kind.dataset.kind);
});

// A kind changed anywhere else - a duplicated row, a browser restoring a form - still has to show on its trigger
document.addEventListener('change', event => {
    const row = event.target.closest?.('[data-kind-row]');
    if (row) refreshTrigger(row);
});

// A module script runs before DOMContentLoaded on first load, but this one is also imported by controllers-admin.js, which EasyAdmin may load later than that
if ('loading' === document.readyState) {
    document.addEventListener('DOMContentLoaded', () => enhanceAll());
} else {
    enhanceAll();
}

// A block row added to the collection, and the kind rows of a container's slots, which arrive with the sub-form the picker itself just loaded
document.addEventListener('ea.collection.item-added', event => {
    enhanceAll(event.detail?.newElement);
    if (event.detail?.newElement) refreshTriggersIn(event.detail.newElement);
});

document.addEventListener('c975l:block-data-loaded', () => {
    enhanceAll();
    refreshTriggersIn(document);
});
