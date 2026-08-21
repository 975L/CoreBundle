/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';
import { addSortGesture } from './pointer-sort.js';
import { MOVE_ICON } from './sort-icon.js';

// Drag-and-drop reordering of the rows of an EasyAdmin index - the counterpart of ea-sortable.js for an entity living as index rows rather than as items of a collection field, where there is no form to renumber and the new order has to be persisted on its own.
// Mounted automatically on <body> by controllers-admin.js, and a no-op on every index whose rows carry no "data-reorder-url": a CRUD opts in by declaring the three attributes below in its own "entity_row_attributes" block (see SiteBundle's collection_item_crud_index.html.twig and ShopBundle's product_crud_index.html.twig).
// The contract with the CRUD controller: "data-reorder-url" is POSTed {group, ids, _token}, ids being the new display order, and answers {positions: {id: position}} - what it actually persisted, which a paginated index reordering one slice of a table cannot deduce on its own. "data-reorder-group" scopes a drag to the rows sharing it, and is left out by an index holding a single sortable set.
export default class extends Controller {
    connect() {
        // Where the row was picked up, read back at the drop or at a refusal: by then it already sits wherever it was dragged to
        this.originParent = null;
        this.originNextSibling = null;

        this.rows().forEach(row => { this.addHandle(row); });
    }

    rows() {
        return [...this.element.querySelectorAll('table.datagrid tbody tr[data-id][data-reorder-url]')];
    }

    // A grip prepended to the "position" cell, the only column whose value the drag actually changes - the gesture is armed on it alone, so the rest of the row keeps working normally (EasyAdmin's own default row action still opens the edit page on a plain click, a drag never turning into one, see pointer-sort.js)
    addHandle(row) {
        const cell = row.querySelector('td[data-column="position"]');
        if (!cell) return;

        const handle = document.createElement('span');
        // "pr-1" is EasyAdmin's own icon spacing, used rather than a rule of ours so the grip sits like every other icon of the table. The cursor and the touch target come from .ui-sort-handle (see sass/management/_block-collection.scss)
        handle.className = 'ui-sort-handle pr-1';
        // Assigned raw on purpose: MOVE_ICON is a literal SVG constant, never a runtime value. The <span class="icon"> wrapper is what EasyAdmin's global CSS sizes and aligns every icon by
        handle.innerHTML = `<span class="icon">${MOVE_ICON}</span>`;
        cell.prepend(handle);

        addSortGesture(handle, {
            item: row,
            onStart: dragged => this.onDragStart(dragged),
            onMove: (dragged, x, y) => this.onDragMove(dragged, x, y),
            onDrop: dragged => this.onDrop(dragged),
            onCancel: dragged => this.revert(dragged),
        });
    }

    onDragStart(row) {
        this.originParent = row.parentElement;
        this.originNextSibling = row.nextElementSibling;
        row.classList.add('ui-dragging-visual');
    }

    // What "dragover" used to give for free: the pointer belongs to the gesture for its whole duration, so the row being flown over receives no event of its own and has to be hit-tested instead
    onDragMove(row, x, y) {
        const under = document.elementFromPoint(x, y);
        const target = under?.closest('tr[data-id][data-reorder-url]');
        if (!target || target === row || !this.sameGroup(target, row)) return;

        const box = target.getBoundingClientRect();
        const before = y < box.top + box.height / 2;
        target.parentElement.insertBefore(row, before ? target : target.nextElementSibling);
    }

    // A drag never crosses from one sortable set to another: an index scoped to a group only ever reorders within it
    sameGroup(row, other) {
        return (row.dataset.reorderGroup || '') === (other.dataset.reorderGroup || '');
    }

    groupRows(row) {
        return this.rows().filter(sibling => this.sameGroup(sibling, row));
    }

    async onDrop(row) {
        row.classList.remove('ui-dragging-visual');

        const ids = this.groupRows(row).map(sibling => parseInt(sibling.dataset.id, 10));

        try {
            const response = await fetch(row.dataset.reorderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ group: row.dataset.reorderGroup || '', ids, _token: row.dataset.reorderToken }),
            });

            if (!response.ok) {
                this.revert(row);
                return;
            }

            this.applyPositions(await response.json());
        } catch {
            this.revert(row);
        }
    }

    // The positions the server actually persisted, written into the cells - the numbers a reordered slice of a paginated table now maps to would otherwise only show up after a manual reload.
    // Matched on the rows' own dataset rather than through an attribute selector: an id is arbitrary text, and building a selector out of it is one escaping mistake away from matching nothing
    applyPositions(payload) {
        const positions = payload?.positions || {};

        this.rows().forEach(row => {
            const position = positions[row.dataset.id];
            if (undefined === position) return;

            const cell = row.querySelector('td[data-column="position"]');
            const label = cell && [...cell.childNodes].find(node => Node.TEXT_NODE === node.nodeType);
            if (label) label.textContent = String(position);
        });
    }

    // The browser took the gesture back, or the server refused the new order: the row goes back where it was picked up rather than staying wherever the pointer had pushed it
    revert(row) {
        row.classList.remove('ui-dragging-visual');

        if (!this.originParent) return;

        if (this.originNextSibling && this.originNextSibling.parentElement === this.originParent) {
            this.originParent.insertBefore(row, this.originNextSibling);
        } else {
            this.originParent.appendChild(row);
        }
    }
}
