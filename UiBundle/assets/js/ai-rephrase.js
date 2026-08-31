/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Works on plain text, rich formatting not being preserved across a rephrase or a translation
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

    get actionEl() {
        return this.element.querySelector('[data-ai-rephrase-target="action"]');
    }

    // The language a translation screen pins, empty on every other screen. Told apart from the one below, which also holds whatever the select offers on an ordinary edit screen
    get pinnedLocale() {
        return this.element.dataset.aiRephraseLocaleValue || '';
    }

    // The language to translate into, empty when the button rephrases. Pinned by a translation screen, which writes one language and offers no choice; picked from the select everywhere else
    get locale() {
        return this.pinnedLocale || (this.actionEl ? this.actionEl.value : '');
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

    // Style and length say nothing about a translation, and the button no longer does what it says: both follow the choice made in the select
    switchAction() {
        const translating = '' !== this.locale;

        [this.styleEl, this.lengthEl].forEach(el => {
            if (el) el.classList.toggle('d-none', translating);
        });

        const button = this.buttonEl;
        if (button) {
            button.textContent = translating
                ? (button.dataset.translateLabel || button.textContent)
                : (button.dataset.rephraseLabel || button.textContent);
        }

        this.hideError();
    }

    run(event) {
        event.preventDefault();

        const field = this.field();
        if (!field) {
            // Unexpected: the target field is gone from the DOM, so it is surfaced rather than silent
            this.showError();
            return;
        }

        const pinned = '' !== this.pinnedLocale;
        const text = this.sourceText(field.read().trim(), pinned);
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
                locale: this.locale,
                style: this.styleEl ? this.styleEl.value : 'neutral',
                length: this.lengthEl ? this.lengthEl.value : 'same',
            }),
        })
            .then(r => r.json())
            .then(data => {
                if (data.text) {
                    // Replaced on a translation screen, where the field holds the prompt this screen offered and anything kept beside the suggestion would be stored as part of the translation; appended everywhere else, the editor keeping the original to pick from
                    field.write(pinned ? data.text : `${text}\n--- ${this.suggestionLabel}\n${data.text}`);
                } else {
                    this.showError();
                }
            })
            .catch(() => this.showError())
            .finally(() => {
                if (button) button.disabled = false;
            });
    }

    // What is actually sent. A translation screen fills the field with the source text between brackets (see ContentTranslator::prompt): the brackets mark a field nobody has written yet, and sent along they would come back translated with it
    sourceText(text, pinned) {
        return pinned && /^\[[\s\S]*\]$/.test(text) ? text.slice(1, -1).trim() : text;
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
        error.textContent = ('' !== this.locale ? (error.dataset.translateMessage || error.dataset.message) : error.dataset.message) || '';
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
