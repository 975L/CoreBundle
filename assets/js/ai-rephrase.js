/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Attached next to a Trix editor or an opted-in plain textarea (see block_theme.html.twig's
// trix_editor_widget/textarea_widget). Works on plain text: rich formatting (bold, links, lists...) is
// not preserved across a rephrase - a deliberate v1 limitation, not an oversight. No client-side key
// validation (see Readme "AI Assistant") - a failure just surfaces the error message below, the button
// itself stays enabled for another try.
//
// Deliberately not using Stimulus's `static targets`/`static values` sugar: with a camelCase identifier
// ("aiRephrase") Stimulus looks for the non-dasherized "data-airephrase-*" attributes, which is easy to
// get wrong on the template side. Plain dataset/querySelector scoped to this.element sidesteps that,
// but it means the names below are the contract: _ai_rephrase.html.twig MUST write the dasherized
// "data-ai-rephrase-*" form, or every value reads back empty and every target is null.
export default class extends Controller {
    get textareaId() {
        return this.element.dataset.aiRephraseTextareaIdValue || '';
    }

    get url() {
        return this.element.dataset.aiRephraseUrlValue || '';
    }

    get csrfToken() {
        return this.element.dataset.aiRephraseCsrfTokenValue || '';
    }

    get suggestionLabel() {
        return this.element.dataset.aiRephraseSuggestionLabelValue || '';
    }

    get styleEl() {
        return this.element.querySelector('[data-ai-rephrase-target="style"]');
    }

    get lengthEl() {
        return this.element.querySelector('[data-ai-rephrase-target="length"]');
    }

    get buttonEl() {
        return this.element.querySelector('[data-ai-rephrase-target="button"]');
    }

    get errorEl() {
        return this.element.querySelector('[data-ai-rephrase-target="error"]');
    }

    run(event) {
        event.preventDefault();

        const field = this.field();
        if (!field) {
            // Genuinely unexpected (the target textarea/trix-editor is gone from the DOM) - surfaced
            // rather than failing silently, unlike the empty-text case below (nothing to do isn't an error)
            this.showError();
            return;
        }

        const text = field.read().trim();
        if (!text) return;

        const button = this.buttonEl;

        this.hideError();
        if (button) button.disabled = true;

        fetch(this.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': this.csrfToken,
            },
            body: new URLSearchParams({
                text,
                style: this.styleEl ? this.styleEl.value : 'neutral',
                length: this.lengthEl ? this.lengthEl.value : 'same',
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.text) {
                    // Appended after the original (separated by "---" and a translated label), never
                    // replacing it - lets the editor mix/pick between both directly in the field instead
                    // of losing the original the moment a rephrase comes back
                    field.write(`${text}\n--- ${this.suggestionLabel}\n${data.text}`);
                } else {
                    this.showError();
                }
            })
            .catch(() => this.showError())
            .finally(() => {
                if (button) button.disabled = false;
            });
    }

    // A Trix field has a <trix-editor input="textareaId"> mirroring the (hidden) textarea - reading/
    // writing must go through its editor API, direct DOM changes wouldn't sync back to it. A plain
    // opted-in textarea (see block_theme.html.twig's textarea_widget) has no such element - read/write
    // its value directly. Returns null when neither is found (field removed from the DOM, a collection
    // row deleted after this controller connected...).
    field() {
        const trixEditor = document.querySelector(`trix-editor[input="${this.textareaId}"]`);
        if (trixEditor && trixEditor.editor) {
            return {
                read: () => trixEditor.editor.getDocument().toString(),
                write: (text) => trixEditor.editor.loadHTML(this.escapeHtml(text)),
            };
        }

        const textarea = document.getElementById(this.textareaId);
        if (textarea) {
            return {
                read: () => textarea.value,
                write: (text) => { textarea.value = text; },
            };
        }

        return null;
    }

    showError() {
        const error = this.errorEl;
        if (!error) return;
        error.textContent = error.dataset.message || '';
        error.classList.remove('d-none');
    }

    hideError() {
        const error = this.errorEl;
        if (!error) return;
        error.classList.add('d-none');
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
