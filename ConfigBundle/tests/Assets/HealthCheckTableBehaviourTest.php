<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\ConfigBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/health-check-table.js filtered, sorted and acknowledged, over the rows templates/management/health_check/_table.html.twig renders
// A table that hides rows rather than removing them is a table whose whole state is invisible to a test reading the file: what is left out, what the count says is left out, and whether an emptied view says so instead of reading as a check that never ran
#[Group('browser')]
class HealthCheckTableBehaviourTest extends JsCase
{
    // The page opens on "unresolved" - what is left to act upon - and not on a status a row stores
    public function testTheTableOpensOnWhatIsLeftToActUpon(): void
    {
        $shown = $this->table('return visible();');

        $this->assertSame(['warning-open', 'error-open'], $shown, 'The table does not open on the warnings and errors nobody has dealt with yet: a conforming row, or one already acknowledged, is on screen.');
    }

    // A filtered table must never read as the whole of what was recorded
    public function testTheCountSaysWhatTheCurrentViewLeavesOut(): void
    {
        $count = $this->table('return { text: root.querySelector("[data-health-check-table-target=hiddenCount]").textContent, hidden: root.querySelector("[data-health-check-table-target=hiddenCount]").hidden };');

        $this->assertSame('2 masquees', $count['text'], 'The count of what the view leaves out is not filled in.');
        $this->assertFalse($count['hidden'], 'The count is hidden while the view is leaving rows out.');
    }

    // Every status shown, and the count goes quiet rather than saying "0"
    public function testShowingEverythingLeavesNothingOutAndSaysNothing(): void
    {
        $all = $this->table(
            'const status = root.querySelector("[data-health-check-table-target=status]");
             status.value = "";
             status.dispatchEvent(new Event("change"));
             await tick();

             return { visible: visible().length, count: root.querySelector("[data-health-check-table-target=hiddenCount]").hidden };'
        );

        $this->assertSame(4, $all['visible'], 'Clearing the status filter does not bring every row back.');
        $this->assertTrue($all['count'], 'The count still speaks while the view leaves nothing out.');
    }

    // The search reads the text a row carries rather than what a cell happens to render
    public function testTheSearchNarrowsOnTheTextARowCarries(): void
    {
        $found = $this->table(
            'const status = root.querySelector("[data-health-check-table-target=status]");
             status.value = "";
             status.dispatchEvent(new Event("change"));
             const filter = root.querySelector("[data-health-check-table-target=filter]");
             filter.value = "ACCUEIL";
             filter.dispatchEvent(new Event("input"));
             await tick();

             return visible();'
        );

        $this->assertSame(['warning-open', 'ok-row'], $found, 'The search does not narrow on the text the rows carry, or it is case-sensitive where a visitor types as they please.');
    }

    // A site with nothing left to act upon lands on an empty default view, which would otherwise read as a check that never ran
    public function testATableWithNothingLeftToActUponSaysSoRatherThanLookingBroken(): void
    {
        $empty = $this->table(
            'const filter = root.querySelector("[data-health-check-table-target=filter]");
             filter.value = "rien du tout";
             filter.dispatchEvent(new Event("input"));
             await tick();

             return { visible: visible().length, said: !root.querySelector("[data-health-check-table-target=empty]").hidden };'
        );

        $this->assertSame(0, $empty['visible']);
        $this->assertTrue($empty['said'], 'A view leaving every row out says nothing, so a table with nothing to act upon reads as a check that never ran.');
    }

    // The dataset is only updated once the server has stored it: a row silently vanishing on a failed request is worse than one that stays
    public function testARowLeavesTheDefaultViewOnlyOnceTheServerHasStoredIt(): void
    {
        $acknowledged = $this->table(
            'root.querySelector("[data-action*=acknowledge]").click();
             await tick();

             return { visible: visible(), icon: root.querySelector("[data-action*=acknowledge] i").className, disabled: root.querySelector("[data-action*=acknowledge]").disabled };'
        );

        $this->assertSame(['error-open'], $acknowledged['visible'], 'An acknowledged row stays in the view of what is left to act upon.');
        $this->assertSame('fa fa-rotate-left', $acknowledged['icon'], 'The button does not turn into the one that takes the acknowledgement back.');
        $this->assertFalse($acknowledged['disabled'], 'The button is left disabled, so the acknowledgement cannot be undone.');
    }

    // A refused request must leave the row exactly where it was rather than hiding it on an acknowledgement nothing recorded
    public function testARefusedAcknowledgementChangesNothingAtAll(): void
    {
        $refused = $this->table(
            'window.__ok = false;
             root.querySelector("[data-action*=acknowledge]").click();
             await tick();

             return { visible: visible(), disabled: root.querySelector("[data-action*=acknowledge]").disabled };'
        );

        $this->assertSame(['warning-open', 'error-open'], $refused['visible'], 'A row was hidden although the server refused to record the acknowledgement.');
        $this->assertFalse($refused['disabled'], 'The button is left disabled after a refused request, so nothing can be tried again.');
    }

    // Sorting is on the cell's own text, and says which way round it went to anything reading the header
    public function testSortingAColumnReordersTheRowsAndAnnouncesItsDirection(): void
    {
        $sorted = $this->table(
            'const status = root.querySelector("[data-health-check-table-target=status]");
             status.value = "";
             status.dispatchEvent(new Event("change"));
             const header = root.querySelectorAll("[data-health-check-table-target=header]")[1];
             header.click();
             await tick();
             const up = visible();
             header.click();
             await tick();

             return { up, down: visible(), sorted: header.getAttribute("aria-sort"), others: [...root.querySelectorAll("[data-health-check-table-target=header][aria-sort]")].length };'
        );

        $this->assertSame(['done-row', 'ok-row', 'error-open', 'warning-open'], $sorted['up'], 'The column is not sorted on the text its cells carry.');
        $this->assertSame(array_reverse($sorted['up']), $sorted['down'], 'Clicking the same header twice does not sort the other way round.');
        $this->assertSame('descending', $sorted['sorted'], 'The header does not say which way the column is sorted.');
        $this->assertSame(1, $sorted['others'], 'More than one header claims to be the sorted one.');
    }

    // The repeated page cell is blanked and the row keeping it marked as its group's first, re-run after every sort and filter
    public function testARepeatedPageIsNamedOnceAndOnlyOnItsGroupsFirstVisibleRow(): void
    {
        $grouped = $this->table(
            'const status = root.querySelector("[data-health-check-table-target=status]");
             status.value = "";
             status.dispatchEvent(new Event("change"));
             await tick();

             return {
                 named: [...root.querySelectorAll("[data-health-check-table-target=label]")].map((l) => l.hidden),
                 first: [...root.querySelectorAll("tbody tr")].map((r) => r.classList.contains("health-check-row-first")),
             };'
        );

        $this->assertSame([false, true, false, true], $grouped['named'], 'A page is named on every one of its rows instead of on the first alone, or not named at all.');
        $this->assertSame([true, false, true, false], $grouped['first'], 'The mark separating one page from the next does not follow the rows actually on screen.');
    }

    private function table(string $probe): mixed
    {
        return $this->observe(
            $this->markup(),
            ['health-check-table' => 'health-check-table'],
            'const tick = () => new Promise((r) => setTimeout(r, 40));
             const visible = () => [...root.querySelectorAll("tbody tr")].filter((r) => !r.hidden).map((r) => r.id);
             ' . $probe,
            [
                // The acknowledge route answered by the scenario: what the controller does with the answer is the point, not the request
                'before' => 'window.__ok = true;
                    window.fetch = () => Promise.resolve({ ok: window.__ok, json: () => Promise.resolve({ acknowledged: true }) });',
                'settle' => 80,
            ]
        );
    }

    // The rows _table.html.twig renders, reduced to what the controller reads: two pages, four rows, one already acknowledged
    private function markup(): string
    {
        $rows = [
            ['warning-open', '/accueil', 'Accueil', 'warning', '0', 'seo', 'Titre trop court'],
            ['ok-row', '/accueil', 'Accueil', 'success', '0', 'seo', 'Description correcte'],
            ['error-open', '/contact', 'Contact', 'error', '0', 'links', 'Lien casse'],
            ['done-row', '/contact', 'Contact', 'warning', '1', 'seo', 'Deja traite'],
        ];

        $body = '';
        foreach ($rows as [$id, $url, $label, $status, $done, $kind, $text]) {
            $body .= sprintf(
                '<tr id="%s" data-health-check-table-target="row" data-url="%s" data-status="%s" data-acknowledged="%s" data-kind="%s" data-search-text="%s">
                    <td><span data-health-check-table-target="label">%s</span></td>
                    <td>%s</td>
                    <td><button type="button" data-action="health-check-table#acknowledge" data-health-check-table-url-param="/ack/%s"><i class="fa fa-check"></i></button></td>
                </tr>',
                $id,
                $url,
                $status,
                $done,
                $kind,
                strtolower($label . ' ' . $text),
                $label,
                $text,
                $id
            );
        }

        return sprintf(
            '<div data-controller="health-check-table" data-health-check-table-token-value="tok" data-health-check-table-hidden-count-message-value="__count__ masquees">
                <input data-health-check-table-target="filter" data-action="input->health-check-table#filterRows">
                <select data-health-check-table-target="status" data-action="change->health-check-table#filterRows">
                    <option value="unresolved" selected>A traiter</option>
                    <option value="">Tout</option>
                    <option value="error">Erreurs</option>
                </select>
                <select data-health-check-table-target="kind" data-action="change->health-check-table#filterRows"><option value="" selected>Tous</option><option value="seo">SEO</option></select>
                <p data-health-check-table-target="hiddenCount"></p>
                <p data-health-check-table-target="empty" hidden>Rien a signaler</p>
                <table>
                    <thead><tr>
                        <th data-health-check-table-target="header" data-action="click->health-check-table#sort">Page</th>
                        <th data-health-check-table-target="header" data-action="click->health-check-table#sort">Constat</th>
                        <th>Action</th>
                    </tr></thead>
                    <tbody>%s</tbody>
                </table>
            </div>',
            $body
        );
    }
}
