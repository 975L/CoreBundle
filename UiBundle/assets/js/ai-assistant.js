/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Plain dataset/querySelector rather than Stimulus targets/values, whose camelCase identifier would want the non-dasherized "data-aiassistant-*"
export default class extends Controller {
    get askUrl() {
        return this.element.dataset.aiAssistantAskUrlValue || '';
    }

    get csrfToken() {
        return this.element.dataset.aiAssistantCsrfTokenValue || '';
    }

    get logEl() {
        return this.element.querySelector('[data-ai-assistant-target="log"]');
    }

    get inputEl() {
        return this.element.querySelector('[data-ai-assistant-target="input"]');
    }

    get submitEl() {
        return this.element.querySelector('[data-ai-assistant-target="submit"]');
    }

    get errorEl() {
        return this.element.querySelector('[data-ai-assistant-target="error"]');
    }

    get pendingEl() {
        return this.element.querySelector('[data-ai-assistant-target="pending"]');
    }

    ask(event) {
        event.preventDefault();

        const input = this.inputEl;
        const submit = this.submitEl;
        const question = input ? input.value.trim() : '';
        if (!question) return;

        this.hideError();
        this.appendEntry('question', question);
        this.showPending();
        if (input) {
            input.value = '';
            input.disabled = true;
        }
        if (submit) submit.disabled = true;

        fetch(this.askUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-CSRF-Token': this.csrfToken,
            },
            body: new URLSearchParams({ question }),
        })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            // An error key ("unavailable", "invalid_csrf") is a diagnostic, not an answer: the reader gets the message the template carries, with its link, rather than that word
            // An answer with no text is one of those failures too, whatever the status code that carried it: rendering it would add an empty line and read as nothing having happened
            .then(({ ok, data }) => ok && 'string' === typeof data.answer && '' !== data.answer.trim()
                ? this.appendEntry('answer', data.answer, data.sources)
                : this.showError())
            .catch(() => this.showError())
            .finally(() => {
                this.hidePending();
                if (input) {
                    input.disabled = false;
                    input.focus();
                }
                if (submit) submit.disabled = false;
            });
    }

    // Built via DOM APIs, not innerHTML: both text and sources come from the network
    appendEntry(kind, text, sources) {
        const log = this.logEl;
        if (!log) return;

        const entry = document.createElement('p');
        entry.className = `ai-assistant__entry ai-assistant__entry--${kind}`;
        entry.textContent = text;
        log.appendChild(entry);

        if (Array.isArray(sources) && sources.length > 0) {
            const list = document.createElement('p');
            list.className = 'ai-assistant__sources';
            sources.forEach((source, index) => {
                if (index > 0) list.appendChild(document.createTextNode(' · '));
                list.appendChild(source.project ? this.buildTourButton(source) : this.buildLink(source));
            });
            log.appendChild(list);
        }

        log.scrollTop = log.scrollHeight;
    }

    // A question the backend has never seen costs it a model call, so the wait is counted in seconds and needs to be visible: without this, a disabled field is the only sign anything is happening
    showPending() {
        const pending = this.pendingEl;
        if (pending) pending.classList.remove('d-none');
    }

    hidePending() {
        const pending = this.pendingEl;
        if (pending) pending.classList.add('d-none');
    }

    // Server-rendered, message and link included: nothing here comes from the response
    showError() {
        const error = this.errorEl;
        if (error) error.classList.remove('d-none');
    }

    hideError() {
        const error = this.errorEl;
        if (error) error.classList.add('d-none');
    }

    buildLink(source) {
        const link = document.createElement('a');
        link.href = source.url;
        link.target = '_blank';
        link.rel = 'noopener';
        link.textContent = source.label;

        return link;
    }

    // The guided-project controller is mounted on every admin page and listens for this attribute, so the parcours starts right here instead of sending the reader off to the dashboard
    buildTourButton(source) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-sm btn-link p-0 align-baseline';
        button.dataset.guidedProjectSlug = source.project;
        button.textContent = source.label;

        return button;
    }
}
