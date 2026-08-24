/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Hand-rolled sort and filter, no DataTables dependency; rows are hidden, never removed
export default class extends Controller {
    static targets = ['row', 'filter', 'status', 'kind', 'header', 'label', 'hiddenCount', 'empty'];
    static values = {
        // Off for the "Site" section, whose site-wide checks belong to no one page
        group: { type: Boolean, default: true },
        // Minted once for the whole table (see _table.html.twig), the acknowledge route checking it under its own route name
        token: String,
        // Carries a literal __count__ the count is substituted into - Twig holds the wording, this only fills the hole
        hiddenCountMessage: String,
    };

    connect() {
        this.sortState = { index: null, ascending: true };
        // The status select opens on "unresolved" (see _table.html.twig), so the first paint has to be filtered too, not just the ones a change event triggers. Guarded: the per-page panel embeds this same table without any filter row
        if (this.hasStatusTarget) {
            this.filterRows();

            return;
        }

        this.updateGrouping();
    }

    filterRows() {
        const text = this.filterTarget.value.trim().toLowerCase();
        const status = this.statusTarget.value;
        const kind = this.kindTarget.value;

        let hidden = 0;

        for (const row of this.rowTargets) {
            const matchesText = !text || row.dataset.searchText.includes(text);
            const matchesKind = !kind || row.dataset.kind === kind;
            row.hidden = !(matchesText && matchesKind && this.matchesStatus(row, status));
            if (row.hidden) hidden += 1;
        }

        this.updateHiddenCount(hidden);
        // A site with nothing left to act upon lands here, the default view hiding every conforming row - an empty table would read as a check that never ran
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = 0 === hidden || hidden !== this.rowTargets.length;
        }
        this.updateGrouping();
    }

    // "unresolved" is the view the page opens on rather than a stored status: what is left to act upon, ie. a warning or an error nobody has declared dealt with yet. Every other value is a plain status match, and the empty one matches everything
    matchesStatus(row, status) {
        if ('unresolved' === status) {
            return '1' !== row.dataset.acknowledged && ['warning', 'error'].includes(row.dataset.status);
        }

        return !status || row.dataset.status === status;
    }

    // Says what the current view is leaving out, so a filtered table never reads as the whole of what was recorded
    updateHiddenCount(hidden) {
        if (!this.hasHiddenCountTarget) {
            return;
        }

        this.hiddenCountTarget.textContent = this.hiddenCountMessageValue.replace('__count__', hidden);
        this.hiddenCountTarget.hidden = 0 === hidden;
    }

    // Declares one row dealt with without re-running anything, or takes that back - the row leaves the default "unresolved" view on the spot, the page it was fixed from staying where it is. The dataset is only updated once the server has stored it, a row silently vanishing on a failed request being worse than one that stays
    async acknowledge(event) {
        const button = event.currentTarget;
        const row = button.closest('tr');
        button.disabled = true;

        try {
            const response = await fetch(event.params.url, {
                method: 'POST',
                headers: { 'X-CSRF-Token': this.tokenValue, 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                return;
            }

            const { acknowledged } = await response.json();
            row.dataset.acknowledged = acknowledged ? '1' : '0';
            button.querySelector('i').className = acknowledged ? 'fa fa-rotate-left' : 'fa fa-check';

            if (this.hasStatusTarget) {
                this.filterRows();
            }
        } finally {
            button.disabled = false;
        }
    }

    sort(event) {
        const header = event.currentTarget;
        const index = this.headerTargets.indexOf(header);
        const ascending = this.sortState.index === index ? !this.sortState.ascending : true;
        this.sortState = { index, ascending };

        const rows = [...this.rowTargets];
        rows.sort((a, b) => {
            const cellA = a.children[index].textContent.trim();
            const cellB = b.children[index].textContent.trim();

            return ascending ? cellA.localeCompare(cellB) : cellB.localeCompare(cellA);
        });

        for (const row of rows) {
            row.parentElement.append(row);
        }

        for (const th of this.headerTargets) th.removeAttribute('aria-sort');
        header.setAttribute('aria-sort', ascending ? 'ascending' : 'descending');

        this.updateGrouping();
    }

    // Blanks a repeated page cell and marks the row keeping it as its group's first, re-run after every sort or filter. That class only separates one page from the next (see sass/management.scss) - it carries no verdict: a row tinted with its group's worst status contradicted its own status badge, and a sort scattering a page's rows across the table made almost every row read as that page's worst one
    updateGrouping() {
        if (!this.groupValue) {
            return;
        }

        let previousUrl = null;

        this.rowTargets.forEach((row, index) => {
            if (!row.hidden) {
                const isFirstOfGroup = row.dataset.url !== previousUrl;
                this.labelTargets[index].hidden = !isFirstOfGroup;
                row.classList.toggle('health-check-row-first', isFirstOfGroup);
                previousUrl = row.dataset.url;
            }
        });
    }
}
