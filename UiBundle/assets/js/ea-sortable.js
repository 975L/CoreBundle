/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";
import { showAdminMessage } from "./admin-modal.js";
import { addToolbarButton } from "./block-toolbar.js";
import { addSortGesture } from "./pointer-sort.js";
import { MOVE_ICON } from "./sort-icon.js";

// Mounted automatically on <body> by controllers-admin.js — no layout override needed.

export default class extends Controller {
    connect() {
        // Where the row was picked up, read back at the drop: by then it already sits wherever it was dragged to
        this.dragOriginField = null;
        this.dragOriginContainer = null;
        this.dragOriginNextSibling = null;

        this.element.querySelectorAll('[data-ea-collection-field]').forEach(field => { this.initField(field); });

        this.restoreOpenPanels();

        this.boundOnItemAdded = this.onItemAdded.bind(this);
        document.addEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    disconnect() {
        document.removeEventListener('ea.collection.item-added', this.boundOnItemAdded);
    }

    initField(field) {
        if (field.dataset.uiSortable || !this.isSortable(field)) return;
        field.dataset.uiSortable = '1';

        if (!this.itemsContainer(field)) return;

        field.querySelectorAll('.field-collection-item').forEach(item => {
            this.addHandle(item);
            this.applyRestriction(item);
        });
    }

    // Called by pointer-sort.js the moment a gesture turns into a real drag
    onDragStart(item) {
        this.dragOriginField = item.closest('[data-ea-collection-field]');
        this.dragOriginContainer = item.parentElement;
        this.dragOriginNextSibling = item.nextElementSibling;

        item.classList.add('ui-dragging-visual');

        if (this.sortGroup(this.dragOriginField)) this.highlightDropTargets(this.dragOriginField);
    }

    // What "dragover" used to give for free. The pointer belongs to the gesture for its whole duration, so nothing under it receives an event of its own any more and the field being flown over has to be hit-tested instead.
    onDragMove(item, x, y) {
        const under = document.elementFromPoint(x, y);
        if (!under) return;

        // The innermost field wins, the way the old handler stopped the event from bubbling: a "slots" field sits inside "blocks", and the outer one would pull the row straight back out
        const field = under.closest('[data-ea-collection-field]');
        if (!field || !this.acceptsDrop(field)) return;

        const container = this.itemsContainer(field);
        if (!container) return;

        const after = this.dragAfter(field, y);
        if (!after) container.appendChild(item);
        else after.parentElement.insertBefore(item, after);
    }

    onDragDrop(item) {
        this.clearDragStyle(item);

        const finalField = item.closest('[data-ea-collection-field]');
        if (finalField === this.dragOriginField) {
            this.updatePositions(finalField);
        } else {
            this.moveAcrossFields(item, finalField, this.dragOriginContainer, this.dragOriginNextSibling);
        }

        this.clearDrag();
    }

    // The browser took the gesture back (a system gesture, a context menu): the row goes back where it was picked up rather than staying wherever the pointer happened to have pushed it
    onDragCancel(item) {
        this.clearDragStyle(item);
        this.revertToOrigin(item, this.dragOriginContainer, this.dragOriginNextSibling);
        this.clearDrag();
    }

    // Cross-field moves are offered between two fields of the same group, whatever that group holds - the Block collections were the first, they are no longer the only one. A field naming no group stays single-field, which is every sortable that never asked for more
    acceptsDrop(field) {
        if (field === this.dragOriginField) return true;

        const group = this.sortGroup(field);

        return !!group && group === this.sortGroup(this.dragOriginField);
    }

    // The group a collection field belongs to, null for one belonging to none
    sortGroup(field) {
        if (!field) return null;

        return field.dataset.uiSortGroup || null;
    }

    // The accordions open at the moment of a move, by their own id: a reload is the only way to rebuild a form whose indices just changed, and it folds every one of them shut
    rememberOpenPanels() {
        const open = [...this.element.querySelectorAll('.accordion-collapse.show[id]')].map(panel => panel.id);

        try {
            window.sessionStorage.setItem(this.panelStorageKey(), JSON.stringify(open));
        } catch {
            // A browser refusing storage loses the panel, not the move
        }
    }

    // Reopened once, then forgotten: the state belongs to the reload that follows a move, not to the screen
    restoreOpenPanels() {
        let open = [];

        try {
            open = JSON.parse(window.sessionStorage.getItem(this.panelStorageKey()) || '[]');
            window.sessionStorage.removeItem(this.panelStorageKey());
        } catch {
            return;
        }

        open.forEach(id => {
            const panel = document.getElementById(id);
            if (!panel) return;

            panel.classList.add('show');
            document.querySelector(`[data-bs-target="#${CSS.escape(id)}"]`)?.classList.remove('collapsed');
        });
    }

    // The screen's own key: two entities edited in two tabs never restore each other's panels
    panelStorageKey() {
        return `ui-open-panels:${window.location.pathname}${window.location.search}`;
    }

    clearDragStyle(item) {
        item.classList.remove('ui-dragging-visual');
    }

    clearDrag() {
        this.dragOriginField = null;
        this.dragOriginContainer = null;
        this.dragOriginNextSibling = null;
        this.clearDropTargetHighlights();
    }

    isSortable(field) {
        return (field.dataset.prototype || '').includes('[position]')
            || !!field.querySelector('[name$="[position]"]');
    }

    // An empty "slots" field has no visible size to aim a drag at, so every eligible target is outlined
    highlightDropTargets(originField) {
        const group = this.sortGroup(originField);
        this.element.querySelectorAll('[data-ea-collection-field]').forEach(field => {
            if (field === originField || this.sortGroup(field) !== group) return;
            const container = this.itemsContainer(field);
            if (container) container.classList.add('ui-drop-target');
        });
    }

    clearDropTargetHighlights() {
        this.element.querySelectorAll('.ui-drop-target').forEach(el => { el.classList.remove('ui-drop-target'); });
    }

    itemsContainer(field) {
        return field.querySelector('.ea-form-collection-items') || field.querySelector('.form-widget-compound');
    }

    addHandle(item) {
        if (item.dataset.uiHandle) return;
        item.dataset.uiHandle = '1';

        const btn = addToolbarButton(item, {
            title: 'Déplacer',
            icon: MOVE_ICON,
            order: 1,
            onClick: () => {},
        });
        if (!btn) return;

        btn.classList.add('ui-sort-handle');

        const gesture = {
            item,
            onStart: dragged => this.onDragStart(dragged),
            onMove: (dragged, x, y) => this.onDragMove(dragged, x, y),
            onDrop: dragged => this.onDragDrop(dragged),
            onCancel: dragged => this.onDragCancel(dragged),
        };
        addSortGesture(btn, gesture);

        // Extended to the whole header bar so grabbing isn't limited to this small icon. Only the toolbar itself (duplicate, delete, this handle) is excluded - EasyAdmin's own title/toggle button covers most of the bar's width, and it must stay grabbable too, it just keeps toggling normally on a plain click since only an actual drag gesture (pointer movement) hijacks it instead of a click.
        // The mouse alone, though: offering the whole bar to a finger takes "touch-action: none" over all of it, and the page would stop scrolling from everywhere a block header sits. At the finger the handle above is the one grab point, which is why it gets a bigger hit area there (see sass/management/_block-collection.scss).
        const header = item.querySelector('.accordion-header');
        if (header) {
            header.classList.add('ui-sort-grabbable');
            addSortGesture(header, { ...gesture, touch: false, ignore: '.ui-row-toolbar' });
        }
    }

    // Hides the (native EasyAdmin) delete button on a row carrying a checked ".ui-field-restricted" marker (see FormFieldType) - reorder stays available (the move handle is untouched), only removal is blocked. Purely a UX guard: the real enforcement is server-side, via the "restricted"/"type" fields both being disabled (see FormFieldType) and CollectionReconciler's caller skipping restricted entries on removal (see ContactFormCrudController).
    applyRestriction(item) {
        const marker = item.querySelector('.ui-field-restricted');
        if (!marker?.checked) return;

        const deleteButton = item.querySelector('.field-collection-delete-button');
        if (deleteButton) deleteButton.classList.add('ui-hidden');
    }

    dragAfter(field, y) {
        const items = [...field.querySelectorAll('.field-collection-item:not(.ui-dragging)')]
            .filter(item => item.closest('[data-ea-collection-field]') === field);
        return items.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset, element: child };
            return closest;
        }, { offset: -Infinity }).element;
    }

    updatePositions(field) {
        [...field.querySelectorAll('.field-collection-item')]
            .filter(item => item.closest('[data-ea-collection-field]') === field)
            .forEach((item, i) => {
                const pos = item.querySelector('[name$="[position]"]');
                if (pos) pos.value = i;
            });
    }

    // Persisted server-side rather than renamed in the form: a resubmit would delete the Block and recreate it empty, losing its media
    // The row is never rewritten in the DOM: its id is posted to the endpoint the group declares, which is what lets two collections of different shapes exchange rows at all - a Block row and a media row hold nothing like the same fields
    async moveAcrossFields(item, finalField, originContainer, originNextSibling) {
        const root = finalField.closest('[data-ui-move-url]');
        const idInput = item.querySelector('[name$="[id]"]');
        const id = idInput ? idInput.value : '';

        // A row not saved yet has no id to relocate against, so it stays out of this mechanism
        if (!id || !root) {
            this.revertToOrigin(item, originContainer, originNextSibling);
            return;
        }

        const body = new URLSearchParams({
            id,
            ownerType: root.dataset.uiMoveOwnerType || '',
            ownerId: root.dataset.uiMoveOwnerId || '',
            target: finalField.dataset.uiMoveTarget || '',
        });

        try {
            const response = await fetch(root.dataset.uiMoveUrl, {
                method: 'POST',
                headers: { 'X-CSRF-Token': root.dataset.uiMoveCsrfToken || '' },
                body,
            });

            if (response.ok) {
                // Reloaded: the rest of the form was built against the pre-move indices and would misalign. What was open is remembered first, so the editor comes back to the panel they were working in rather than to a form folded shut
                this.rememberOpenPanels();
                window.location.reload();
                return;
            }

            this.revertToOrigin(item, originContainer, originNextSibling);
            this.showFailure(root, await this.refusalReason(response));
        } catch {
            this.revertToOrigin(item, originContainer, originNextSibling);
            this.showFailure(root);
        }
    }

    // What the server answered it refused the move for, when it is something the editor can act on (see BlockMoveController); nothing for a technical failure, which only ever reads as noise next to "the move failed"
    async refusalReason(response) {
        try {
            return (await response.json())?.message || '';
        } catch {
            return '';
        }
    }

    showFailure(root, reason = '') {
        showAdminMessage(root.dataset.uiMoveFailedLabel || '', reason, root.dataset.uiMoveCloseLabel || 'OK');
    }

    revertToOrigin(item, originContainer, originNextSibling) {
        if (originNextSibling && originNextSibling.parentElement === originContainer) {
            originContainer.insertBefore(item, originNextSibling);
        } else {
            originContainer.appendChild(item);
        }
    }

    onItemAdded(e) {
        const newElement = e.detail?.newElement;
        if (newElement) {
            this.addHandle(newElement);
            const field = newElement.closest('[data-ea-collection-field]');
            if (field) {
                const pos = newElement.querySelector('[name$="[position]"]');
                if (pos) {
                    const siblings = [...field.querySelectorAll('.field-collection-item')]
                        .filter(item => item.closest('[data-ea-collection-field]') === field);
                    pos.value = siblings.length - 1;
                }
            }
        }
        this.element.querySelectorAll('[data-ea-collection-field]').forEach(field => { this.initField(field); });
    }
}
