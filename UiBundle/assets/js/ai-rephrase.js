/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Works on plain text, rich formatting not being preserved across a rephrase
// Plain dataset/querySelector rather than Stimulus targets/values, whose camelCase identifier would want the non-dasherized "data-airephrase-*"
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
            // Unexpected: the target field is gone from the DOM, so it is surfaced rather than silent
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
                    // Appended, never replacing: the editor keeps the original to pick from
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

    // A Trix field must go through its editor API, direct DOM changes not syncing back to it; null when neither element is found
    field() {
        const trixEditor = document.querySelector(`trix-editor[input="${this.textareaId}"]`);
        if (trixEditor?.editor) {
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
