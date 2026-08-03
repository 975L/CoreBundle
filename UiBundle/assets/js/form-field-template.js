/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Mounted automatically on <body> by controllers-admin.js — adds a "pick a ready-made field" select next to a Form's own "fields" collection "+ Add" button (see FormCrudController, the only CollectionField carrying a "data-form-field-template-catalog-url" via its "row_attr" form option). Picking a template appends a fresh FormField row through the collection's own native "add" button (so ea-sortable.js's own item-added handling still runs, e.g. position numbering) then fills it client-side - no DB write, no page reload, works on a form that hasn't been saved yet.
export default class extends Controller {
    connect() {
        this.element.querySelectorAll('[data-ea-collection-field]').forEach(field => this.initField(field));
    }

    initField(field) {
        if (field.dataset.uiTemplatePicker) return;
        const url = field.dataset.formFieldTemplateCatalogUrl;
        if (!url) return;
        field.dataset.uiTemplatePicker = '1';

        const addButton = this.ownAddButton(field);
        if (!addButton) return;

        const select = document.createElement('select');
        select.className = 'form-select ui-form-field-template-picker';
        select.style.display = 'inline-block';
        select.style.width = 'auto';
        select.style.marginLeft = '.5rem';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = field.dataset.formFieldTemplatePickerPlaceholder || 'Add from a template…';
        select.appendChild(placeholder);

        fetch(url, { headers: { Accept: 'application/json' } })
            .then(response => (response.ok ? response.json() : []))
            .then(templates => {
                if (!Array.isArray(templates) || !templates.length) return;

                templates.forEach((template, i) => {
                    const option = document.createElement('option');
                    option.value = String(i);
                    option.textContent = template.fieldLabel;
                    select.appendChild(option);
                });

                select.addEventListener('change', () => {
                    const template = templates[Number(select.value)];
                    select.value = '';
                    if (template) this.addFromTemplate(field, addButton, template);
                });

                addButton.insertAdjacentElement('afterend', select);
            })
            .catch(() => {}); // best-effort: no picker rather than a broken page if the catalog can't be fetched
    }

    // Same technique as block-duplicate.js's duplicateBlock/duplicateMedia: click the collection's own native "add" button to get a fresh, correctly-indexed empty row, then fill it in place.
    addFromTemplate(field, addButton, template) {
        addButton.click();

        const items = [...field.querySelectorAll('.field-collection-item')]
            .filter(item => item.closest('[data-ea-collection-field]') === field);
        const newItem = items[items.length - 1];
        if (!newItem) return;

        this.setValue(newItem, 'label', template.fieldLabel);
        this.setValue(newItem, 'placeholder', template.placeholder);
        this.setChecked(newItem, 'required', !!template.required);
        this.setValue(newItem, 'type', template.type);

        newItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    setValue(item, localName, value) {
        const input = item.querySelector(`[name$="[${localName}]"]`);
        if (input) input.value = value || '';
    }

    setChecked(item, localName, checked) {
        const input = item.querySelector(`[name$="[${localName}]"]`);
        if (input) input.checked = checked;
    }

    // Finds the "add" button belonging directly to `field`, never one from a collection nested inside one of its own rows - same guard as block-duplicate.js's own ownAddButton.
    ownAddButton(field) {
        return [...field.querySelectorAll('.field-collection-add-button')]
            .find(button => button.closest('[data-ea-collection-field]') === field);
    }
}
