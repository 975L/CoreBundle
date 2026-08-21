/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Mounted automatically on <body> by controllers-admin.js — no layout override needed. Same idea as block-focus.js, for a plain form field rather than a block row: a link pointing here with a "focusField=<property>" query param (see SiteBundle's PageHealthCheckAdviceBuilder, whose health check advice sends the user straight to the field to fill) opens that field's own tab, scrolls to it and focuses it, instead of dropping the user at the top of a form holding dozens of fields.
export default class extends Controller {
    connect() {
        const field = new URLSearchParams(window.location.search).get('focusField');
        if (!field) return;

        // EasyAdmin names every field "<FormName>[<property>]", the form name varying with the entity - matching on the suffix alone keeps this free of it
        const target = this.element.querySelector(`[name$="[${field}]"]`) ?? this.collection(field);
        if (!target) return;

        // A field sitting in a non-active tab has no size to scroll to until its own tab is shown. EasyAdmin's tabs are Bootstrap ones pointing at their pane through href (see its Tabs/Item.html.twig), collapsible sections through data-bs-target - both looked up, so this keeps working whichever it renders
        const pane = target.closest('.tab-pane');
        const trigger = pane && document.querySelector(`[data-bs-toggle="tab"][href="#${pane.id}"], [data-bs-target="#${pane.id}"]`);
        if (trigger) trigger.click();

        // One frame later: showing a tab moves the focus itself (Bootstrap's own handling, and the fragment navigation of an <a href="#pane"> trigger), which would take it straight back off the field
        requestAnimationFrame(() => {
            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
            // A no-op on the collection row found below, which is a <div> nothing can focus - it is scrolled to, and the row it opens on is where the caret belongs anyway
            target.focus({ preventScroll: true });
        });
    }

    // A CollectionField prints no name, no id and no "for" of its own - EasyAdmin renders its label as a <legend> (see its crud/form_theme.html.twig), so the suffix match above never finds one. Its entries carry the property name instead ("Book[videos][0][url]"), and its prototype does when it holds none: the whole row is returned rather than that first input, an entry sitting in a collapsed accordion having nowhere to scroll to
    collection(field) {
        const entry = this.element.querySelector(`[name*="[${field}]["]`);

        return entry?.closest('[data-ea-collection-field]') ?? this.element.querySelector(`[data-prototype*="[${field}]["]`);
    }
}
