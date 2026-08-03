/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { loadBlockData } from "./block.js";
import { addToolbarButton } from "./block-toolbar.js";

// No width/height here, deliberately - see the same note in ea-sortable.js: EasyAdmin's own icons rely entirely on its global ".icon svg" CSS for sizing, so this needs to too, to stay consistent.
const ICON_COPY = '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">'
    + '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>'
    + '</svg>';

// Mounted automatically on <body> by controllers-admin.js — no layout override needed. Adds a "Duplicate" button to each block row AND to each individual media row inside a block. Both insert a full copy right below the source (same fields, same media/files) - everything client-side, no DB write on click, so this also works on a page that hasn't been saved yet.
export default class extends Controller {
    connect() {
        this.element.querySelectorAll('[data-ea-collection-field]').forEach(field => this.initField(field));

        this.boundOnItemAdded = this.onItemAdded.bind(this);
        document.addEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    disconnect() {
        document.removeEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    onItemAdded(event) {
        const newElement = event.detail && event.detail.newElement;
        if (newElement) this.addButtonFor(newElement);
    }

    initField(field) {
        this.collectionItems(field).forEach(item => this.addButtonFor(item));
    }

    // A row is either a block (has its own kind selector), a media entry (has a raw file input), or - anywhere inside a block's own kind-specific data form - a plain nested collection item (a skill bar, a process step, a card...). Anything else is some unrelated EasyAdmin collection elsewhere in the admin (this controller is mounted on <body>, so it sees every collection in the whole admin) and is left alone. Not keying off Vich's wrapper class: EasyAdmin renders its own Vich widget theme with classes like "ea-vich-image"/"ea-vich-file", not VichUploaderBundle's own default "vich-image"/"vich-file".
    addButtonFor(item) {
        if (item.querySelector('[data-kind-row]')) {
            this.addButton(item, () => this.duplicateBlock(item));
        } else if (item.querySelector('input[type="file"]')) {
            this.addButton(item, () => this.duplicateMedia(item));
        } else if (item.closest('.block-data-form')) {
            this.addButton(item, () => this.duplicateItem(item));
        }
    }

    addButton(item, onClick) {
        if (item.dataset.uiDuplicateBtn) return;
        item.dataset.uiDuplicateBtn = '1';

        // Shared with ea-sortable.js's move handle, so both plus EasyAdmin's own delete button always end up grouped in one toolbar (move, duplicate, delete - see block-toolbar.js), regardless of which of these scripts happens to run first for a given row.
        addToolbarButton(item, {
            title: 'Dupliquer',
            icon: ICON_COPY,
            order: 2,
            onClick,
        });
    }

    // Finds the "add" button belonging directly to `field`, never one from a collection nested inside one of its own items (e.g. a block's own media collection also has an "add" button, and it sits earlier in the DOM than the outer "add block" button - a plain field.querySelector() would grab that one instead).
    ownAddButton(field) {
        return [...field.querySelectorAll('.field-collection-add-button')]
            .find(button => button.closest('[data-ea-collection-field]') === field);
    }

    duplicateBlock(sourceItem) {
        const field = sourceItem.closest('[data-ea-collection-field]');
        const kindSelect = sourceItem.querySelector('[data-kind-row] select');
        const kind = kindSelect && kindSelect.value;
        if (!field || !kind) return;

        const addButton = this.ownAddButton(field);
        if (!addButton) return;

        const payload = this.buildDataPayload(sourceItem);

        // The click synchronously appends a fresh empty row and fires ea.collection.item-added - suppress block-collection.js's auto-scroll/focus for it, we drive this row ourselves.
        field.dataset.uiSuppressAutofocus = '1';
        addButton.click();
        delete field.dataset.uiSuppressAutofocus;

        const items = this.collectionItems(field);
        const newItem = items[items.length - 1];
        if (!newItem || newItem === sourceItem) return;

        sourceItem.after(newItem);
        this.renumberPositions(field);
        newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

        const newSelect = newItem.querySelector('[data-kind-row] select');
        newSelect.value = kind;

        loadBlockData(newSelect, newSelect.dataset.blockKindUrlValue, kind, payload)
            .then(() => this.copyMediaFiles(sourceItem, newItem));
    }

    // A media row has no dynamic sub-form to (re)load - its fields are already fully rendered, so duplicating it is a straight DOM-to-DOM copy, no server round-trip needed.
    duplicateMedia(sourceItem) {
        const field = sourceItem.closest('[data-ea-collection-field]');
        if (!field) return;

        const addButton = this.ownAddButton(field);
        if (!addButton) return;

        addButton.click();

        const items = this.collectionItems(field);
        const newItem = items[items.length - 1];
        if (!newItem || newItem === sourceItem) return;

        sourceItem.after(newItem);
        this.renumberPositions(field);
        newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

        this.copyMediaValues(sourceItem, newItem);
        this.copyMediaFile(sourceItem, newItem);
    }

    // A plain nested collection item (e.g. one of SectionSkillsType's "skills", ProcessStepsType's "steps"...) - already fully rendered like a media row, so this is the same straight DOM-to-DOM copy, no server round-trip needed.
    duplicateItem(sourceItem) {
        const field = sourceItem.closest('[data-ea-collection-field]');
        if (!field) return;

        const addButton = this.ownAddButton(field);
        if (!addButton) return;

        addButton.click();

        const items = this.collectionItems(field);
        const newItem = items[items.length - 1];
        if (!newItem || newItem === sourceItem) return;

        sourceItem.after(newItem);
        this.renumberPositions(field);
        newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });

        this.copyItemValues(sourceItem, newItem);
        // Re-runs Trix setup for any not-yet-initialized textarea in the new row (e.g. a card's or step's rich-text field) now that its value has already been copied, so that copied value becomes the editor's initial content instead of an empty one.
        document.dispatchEvent(new CustomEvent('c975l:block-data-loaded'));
    }

    // Matched by position rather than by field name: both rows come from the exact same entry form type, so they always carry the same fields in the same order - no name-parsing needed here (unlike copyMediaValues, which has to line up two rows that can legitimately differ, e.g. an existing media's Vich "delete" checkbox that a fresh row doesn't have yet).
    copyItemValues(sourceItem, targetItem) {
        const copyable = el => el.name && !el.disabled && el.type !== 'file';

        const sourceFields = [...sourceItem.querySelectorAll('input, select, textarea')].filter(copyable);
        const targetFields = [...targetItem.querySelectorAll('input, select, textarea')].filter(copyable);

        sourceFields.forEach((source, i) => {
            const target = targetFields[i];
            if (target) this.copyFieldValue(source, target);
        });
    }

    copyFieldValue(source, target) {
        if (source.type === 'checkbox' || source.type === 'radio') {
            target.checked = source.checked;
            return;
        }
        if (source.multiple && target.multiple) {
            [...target.options].forEach(opt => {
                opt.selected = [...source.selectedOptions].some(sOpt => sOpt.value === opt.value);
            });
            return;
        }
        target.value = source.value;
        target.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Copies the source row's "data" field values (renamed to the plain "data[...]" shape BlockFormController::dataForm expects), so the freshly-injected sub-form comes back pre-filled - including arbitrarily nested fields (e.g. a repeatable field inside "data") since Symfony's own form rendering does that from bound data. Medias are deliberately NOT included here (see copyMediaFiles): MediaUploadType has `data_class: Media`, and Symfony's form component refuses to use a plain array as a data_class-bound form's data ("the form's view data is expected to be an instance of Media, but is an array") - that only works for "data" because those kind-specific form types have `data_class: null`.
    buildDataPayload(sourceItem) {
        const params = new URLSearchParams();

        sourceItem.querySelectorAll('.block-data-form input, .block-data-form select, .block-data-form textarea')
            .forEach(el => {
                if (!el.name || el.disabled || el.type === 'file') return;
                if (el.name.includes('[medias]')) return; // handled entirely client-side, see copyMediaFiles

                const marker = '[data]';
                const idx = el.name.indexOf(marker);
                if (idx === -1) return;
                this.appendField(params, 'data' + el.name.slice(idx + marker.length), el);
            });

        return params;
    }

    appendField(params, name, el) {
        if (el.type === 'checkbox' || el.type === 'radio') {
            if (el.checked) params.append(name, el.value);
            return;
        }
        if (el.multiple) {
            [...el.selectedOptions].forEach(opt => params.append(name, opt.value));
            return;
        }
        params.append(name, el.value);
    }

    // The part of a media field's name after its own item's index bracket, e.g. "blocks][3][medias][7][alt]" -> "[alt]" - used to match "the same field" between two media rows regardless of their respective index in the collection.
    mediaLocalName(name) {
        const marker = '[medias][';
        const idx = name.indexOf(marker);
        if (idx === -1) return null;
        const closeBracket = name.indexOf(']', idx + marker.length);
        return name.slice(closeBracket + 1);
    }

    // Copies one media row's non-file field values into another (both already fully rendered, no sub-form to load) - matched by local field name rather than by position, since a source media with an existing file has an extra Vich "delete" checkbox a fresh target row doesn't have yet.
    copyMediaValues(sourceItem, targetItem) {
        // A field's local name alone isn't a unique key: a multi-checkbox field like "cssClasses[]" (rounded corners, thumbnail, etc.) renders several checkboxes sharing that exact same name, one per possible value - so the value has to be part of the key too, or every checkbox collapses onto whichever one happens to be seen last and only it gets matched/copied.
        const keyOf = el => {
            const name = el.name && this.mediaLocalName(el.name);
            if (!name) return null;
            return (el.type === 'checkbox' || el.type === 'radio') ? `${name}=${el.value}` : name;
        };

        const sourceByKey = new Map();
        sourceItem.querySelectorAll('input, select, textarea').forEach(el => {
            const key = keyOf(el);
            if (key) sourceByKey.set(key, el);
        });

        targetItem.querySelectorAll('input, select, textarea').forEach(el => {
            if (!el.name || el.type === 'file') return;
            const key = keyOf(el);
            const source = key && sourceByKey.get(key);
            if (!source || source.type === 'file') return;

            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = source.checked;
                return;
            }
            if (el.multiple && source.multiple) {
                [...el.options].forEach(opt => {
                    opt.selected = [...source.selectedOptions].some(sOpt => sOpt.value === opt.value);
                });
                return;
            }
            el.value = source.value;
        });
    }

    // The freshly-loaded block starts with an empty medias collection (see buildDataPayload - it's never pre-filled from the server). So for each source media, first click the target's own "add" button to get a normal empty prototype row (the exact same thing a manual click on that button does, no data_class conflict since nothing but the prototype markup is involved), then reuse the same value/file copy already proven correct for duplicating a single media entry directly.
    copyMediaFiles(sourceItem, newItem) {
        const sourceMediasField = this.mediasFieldIn(sourceItem);
        const targetMediasField = this.mediasFieldIn(newItem);
        if (!sourceMediasField || !targetMediasField) return;

        const sourceItems = this.collectionItems(sourceMediasField);
        if (!sourceItems.length) return;

        const addButton = this.ownAddButton(targetMediasField);
        if (!addButton) return;
        sourceItems.forEach(() => addButton.click());

        this.collectionItems(targetMediasField).forEach((targetMediaItem, i) => {
            if (!sourceItems[i]) return;
            this.copyMediaValues(sourceItems[i], targetMediaItem);
            this.copyMediaFile(sourceItems[i], targetMediaItem);
        });
    }

    // Locates a block's nested "medias" CollectionType field without assuming anything about its generated id (that assumption was wrong before: id patterns for dynamically-injected fields aren't guaranteed the way a literal "_medias" suffix implied, which silently broke every media duplication path even though "add" of a brand new one worked). A collection field's own prototype HTML is a reliable fingerprint instead - only the medias field renders a file input.
    mediasFieldIn(item) {
        return [...item.querySelectorAll('[data-ea-collection-field]')]
            .find(field => /type=(["'])file\1/.test(field.dataset.prototype || ''));
    }

    copyMediaFile(sourceMediaItem, targetMediaItem) {
        const sourceInput = sourceMediaItem.querySelector('input[type="file"]');
        const targetInput = targetMediaItem.querySelector('input[type="file"]');
        if (!sourceInput || !targetInput) return;

        if (sourceInput.files && sourceInput.files.length) {
            this.setFiles(targetInput, [...sourceInput.files]);
            return;
        }

        const url = this.existingFileUrl(sourceMediaItem);
        if (!url) return;

        fetch(url)
            .then(r => r.blob())
            .then(blob => {
                const filename = decodeURIComponent(url.split('/').pop().split('?')[0]) || 'file';
                this.setFiles(targetInput, [new File([blob], filename, { type: blob.type })]);
            })
            .catch(() => {}); // best-effort: leave that one media without a file rather than break the page
    }

    // EasyAdmin shows an existing image as an <img src="..."> thumbnail wrapped in a lightbox link whose own href is just "#" (a JS hook, not the real URL) - so the actual URL has to come from the <img>, not the <a>. For a non-image file there's no thumbnail, so fall back to any real link.
    existingFileUrl(mediaItem) {
        const img = mediaItem.querySelector('img[src]');
        if (img) return img.src;

        const link = [...mediaItem.querySelectorAll('a[href]')]
            .find(a => a.getAttribute('href') && a.getAttribute('href') !== '#');
        return link ? link.href : null;
    }

    setFiles(input, files) {
        const dataTransfer = new DataTransfer();
        files.forEach(file => dataTransfer.items.add(file));
        input.files = dataTransfer.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    collectionItems(field) {
        return [...field.querySelectorAll('.field-collection-item')]
            .filter(item => item.closest('[data-ea-collection-field]') === field);
    }

    renumberPositions(field) {
        this.collectionItems(field).forEach((item, i) => {
            const pos = item.querySelector('[name$="[position]"]');
            if (pos) pos.value = i;
        });
    }
}
