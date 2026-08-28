/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Mounted automatically on <body> by controllers-admin.js — turns the variable names a calculator's formulas may read into buttons that insert them at the cursor (see FormCrudController, the only CollectionField carrying "data-form-outputs-variables" via its "row_attr" form option). A name is a slug derived from a field's label, so it is neither guessable nor short: typed from memory it is the one thing an admin gets wrong, and the parser can only say so after the save. Built here rather than server-side so the help text under the collection stays readable with no JavaScript at all — the list is the same, it just can't be clicked.
export default class extends Controller {
    connect() {
        this.focused = null;
        // Kept on the controller so disconnect() removes the very listener connect() added, a bound function being a new object each time
        this.onFocus = (event) => {
            if (this.isExpression(event.target)) {
                this.focused = event.target;

                return;
            }

            // Anything else the admin reaches is them leaving the formula, and the chips have nowhere to write again - without this, a click in a label field followed by a chip lands the name in the formula they were in two clicks ago, where nobody is looking. The bar itself is not "anything else": a keyboard reaches a chip by tabbing onto it
            if (!event.target.closest?.(".ui-formula-variables")) {
                this.focused = null;
            }
        };
        this.element.addEventListener("focusin", this.onFocus);

        this.element.querySelectorAll("[data-form-outputs-variables]").forEach((field) => this.build(field));
    }

    disconnect() {
        this.element.removeEventListener("focusin", this.onFocus);
    }

    // The formula input of an output row, as FormOutputType names it - and never the label or the unit beside it
    isExpression(element) {
        return element instanceof HTMLInputElement && /\[expression\]$/.test(element.name || "");
    }

    build(field) {
        // A collection row is cloned from its own prototype on every "+ Add", and EasyAdmin re-renders nothing else: the guard is what keeps a second bar from being built when this controller reconnects
        if (field.dataset.uiFormulaVariables) {
            return;
        }

        let names;
        try {
            names = JSON.parse(field.dataset.formOutputsVariables);
        } catch {
            return;
        }
        if (!Array.isArray(names) || !names.length) {
            return;
        }
        field.dataset.uiFormulaVariables = "1";

        // Styling carried by .ui-formula-variables (sass/management/_form-fields.scss): a style written from JS is never authorized by the nonce EasyAdmin's layout puts on style-src
        const bar = document.createElement("div");
        bar.className = "ui-formula-variables";

        const hint = document.createElement("span");
        hint.className = "ui-formula-variables__hint";
        hint.textContent = field.dataset.formOutputsVariablesHint || "";
        bar.appendChild(hint);

        names.forEach((name) => bar.appendChild(this.chip(name)));

        // Above the rows rather than under them: the bar has to stay in sight of the formula being written
        field.querySelector(".ea-form-collection-items")?.before(bar);
    }

    chip(name) {
        const button = document.createElement("button");
        // "button", never the submit an unqualified <button> is inside a form: clicking a variable would save the Form
        button.type = "button";
        button.className = "btn btn-sm ui-formula-variable";
        button.textContent = name;
        // Pressing the mouse down is what would take the focus out of the formula, and with it the cursor position the name is inserted at - the click still fires, on a field that never stopped being focused
        button.addEventListener("mousedown", (event) => event.preventDefault());
        button.addEventListener("click", () => this.insert(name));

        return button;
    }

    insert(name) {
        // A keyboard reaches the chip by tabbing to it, which does move the focus - hence the field remembered on the way in, rather than document.activeElement read here
        const input = this.focused;
        if (!input || !input.isConnected) {
            return;
        }

        const start = input.selectionStart ?? input.value.length;
        const end = input.selectionEnd ?? start;
        input.value = input.value.slice(0, start) + name + input.value.slice(end);

        // Left where the inserted name ends, so a second chip lands after the first and not inside it
        const caret = start + name.length;
        input.setSelectionRange(caret, caret);
        input.focus();
        // What a formula field's own validation, and any listener a site adds, are waiting for: a value written from script fires nothing on its own
        input.dispatchEvent(new Event("input", { bubbles: true }));
    }
}
