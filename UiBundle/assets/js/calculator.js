/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Keeps a calculator Form's results in step with its inputs. The arithmetic itself stays in PHP (see CalculatorController/ExpressionEvaluator): a formula an admin typed has one implementation, and this controller only carries values there and answers back. The page already renders correct results server-side, so nothing here is needed for the calculator to be right - only for it to follow along
export default class extends Controller {
    static targets = ["results", "reset"];

    static values = { url: String };

    // The only controls a formula can read (see ExpressionEvaluator) - a calculator given an action also carries a name, an email and a message, which must never ride the query string of a GET into the server's logs, nor send a request per keystroke typed in them
    static COMPUTED = 'input[type="number"][name], input[type="range"][name], input[type="checkbox"][name], select[name]';

    // Long enough that dragging a slider sends a handful of requests rather than one per pixel, short enough to read as immediate
    static DEBOUNCE = 200;

    connect() {
        this.timer = null;
        this.controller = null;
        this.readouts = new Map();
        this.onInput = (event) => {
            if (!event.target.matches(this.constructor.COMPUTED)) {
                return;
            }
            this.readout(event.target);
            this.writeUrl();
            this.toggleReset();
            this.schedule();
        };
        this.element.addEventListener("input", this.onInput);
        this.element.addEventListener("change", this.onInput);
        // A shared link carries the choices it was made with, set back on the controls before anything is read
        this.applyUrl();
        this.element.querySelectorAll('input[type="range"]').forEach((slider) => { this.readout(slider); });

        // A shared link, or a reload in Firefox restoring the controls the visitor had moved, leaves results printed for the defaults: they are asked again rather than left contradicting the controls
        if (this.restored()) {
            this.refresh();
        }
        this.toggleReset();
    }

    // The way back to the defaults is offered only once there is somewhere to come back from
    toggleReset() {
        if (this.hasResetTarget) {
            this.resetTarget.hidden = !this.restored();
        }
    }

    // Puts every control back to what the page was rendered with, clears the address and asks the results again: a shared link otherwise leaves no way back but retyping it
    reset() {
        this.element.querySelectorAll(this.constructor.COMPUTED).forEach((input) => {
            if ("checkbox" === input.type) {
                input.checked = input.defaultChecked;
            } else if ("SELECT" === input.tagName) {
                [...input.options].forEach((option) => { option.selected = option.defaultSelected; });
            } else {
                input.value = input.defaultValue;
            }
            this.readout(input);
        });
        this.writeUrl();
        this.toggleReset();
        this.refresh();
    }

    // The name the server knows a control by, what sits inside the brackets of "form_submission[prix-de-l-essence]"
    key(input) {
        return input.name.match(/\[([^\]]+)\]$/)?.[1] ?? null;
    }

    // Sets the controls from the page's own query string, "?boutique-en-ligne=1&type-de-site=3000" - a value no option offers is left alone
    applyUrl() {
        const url = new URLSearchParams(window.location.search);
        this.element.querySelectorAll(this.constructor.COMPUTED).forEach((input) => {
            const key = this.key(input);
            if (null === key || !url.has(key)) {
                return;
            }
            if ("checkbox" === input.type) {
                input.checked = "1" === url.get(key);
            } else if ("SELECT" !== input.tagName || [...input.options].some((option) => option.value === url.get(key))) {
                input.value = url.get(key);
            }
        });
    }

    // Writes the choices that differ from the defaults into the address, so it can be shared as is and opened on the same estimate - the controls left at their default stay out of it, and whatever else the query string held is kept
    writeUrl() {
        const url = new URLSearchParams(window.location.search);
        this.element.querySelectorAll(this.constructor.COMPUTED).forEach((input) => {
            const key = this.key(input);
            if (null === key) {
                return;
            }
            url.delete(key);
            if (this.changed(input)) {
                url.set(key, "checkbox" === input.type ? (input.checked ? "1" : "0") : input.value);
            }
        });
        const query = url.toString();
        window.history.replaceState(window.history.state, "", `${window.location.pathname}${query ? `?${query}` : ""}${window.location.hash}`);
    }

    // Whether any control shows something other than what the page was rendered with
    restored() {
        return [...this.element.querySelectorAll(this.constructor.COMPUTED)].some((input) => this.changed(input));
    }

    // Whether one control shows something other than what the page was rendered with
    changed(input) {
        if ("checkbox" === input.type) {
            return input.checked !== input.defaultChecked;
        }
        if ("SELECT" === input.tagName) {
            return [...input.options].some((option) => option.selected !== option.defaultSelected);
        }

        return input.value !== input.defaultValue;
    }

    disconnect() {
        this.element.removeEventListener("input", this.onInput);
        this.element.removeEventListener("change", this.onInput);
        this.readouts.forEach((readout) => { readout.remove(); });
        this.readouts.clear();
        clearTimeout(this.timer);
        this.controller?.abort();
    }

    // A range input shows no value of its own, in any browser - so the one being dragged is written beside it. Built here rather than in the template: without JavaScript the slider cannot be moved to a value worth reading anyway, and an empty box would be the only thing left of it
    readout(input) {
        if (input.type !== "range") {
            return;
        }

        let readout = this.readouts.get(input);
        if (!readout) {
            readout = document.createElement("output");
            readout.className = "ui-calculator-readout";
            readout.htmlFor = input.id;
            input.after(readout);
            this.readouts.set(input, readout);
        }
        readout.textContent = this.grouped(input.value);
    }

    // Grouped the way the page's own language groups a number - "15 000" on a French page, never "15000". The document's language and not the browser's: this sits beside results the server formatted in that same language, and a visitor arriving with an English browser must not read the two differently
    grouped(value) {
        const number = Number(value);

        return Number.isFinite(number) ? number.toLocaleString(document.documentElement.lang || undefined) : value;
    }

    schedule() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.refresh(), this.constructor.DEBOUNCE);
    }

    async refresh() {
        // The answer to a value that has already been typed over is noise, and two in-flight requests can land out of order
        this.controller?.abort();
        this.controller = new AbortController();

        try {
            const response = await fetch(`${this.urlValue}?${this.parameters()}`, {
                signal: this.controller.signal,
                headers: { Accept: "application/json" },
            });
            if (!response.ok) {
                return;
            }
            this.paint(await response.json());
        } catch {
            // An aborted request, an offline browser: the results simply stay on the last numbers that were right, which beats blanking them
        }
    }

    // The field's own name, as the server knows it - the inputs are named "form_submission[prix-de-l-essence]", the evaluator is keyed by what sits inside the brackets
    parameters() {
        const parameters = new URLSearchParams();
        this.element.querySelectorAll(this.constructor.COMPUTED).forEach((input) => {
            const key = this.key(input);
            if (null !== key) {
                // A checkbox's value is "1" whether ticked or not: its state is what a formula reads, and an unticked one must still be sent, or the server would take its default
                parameters.set(key, "checkbox" === input.type ? (input.checked ? "1" : "0") : input.value);
            }
        });

        return parameters.toString();
    }

    // Formatted server-side and printed as received: the currency, the decimals and the unit are the output's own settings, and re-deriving them here is where the two sides would drift
    paint(results) {
        this.resultsTarget.querySelectorAll("[data-ui-calculator-output]").forEach((cell) => {
            const result = results[cell.dataset.uiCalculatorOutput];
            cell.textContent = result?.formatted ?? "—";
            // A detail line of an option left unticked is worth nothing and says nothing - see FormOutput::$hiddenWhenZero
            const row = cell.closest("[data-ui-calculator-hide-zero]");
            if (row) {
                row.hidden = !result?.value;
            }
        });
    }
}
