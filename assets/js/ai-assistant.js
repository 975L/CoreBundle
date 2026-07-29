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

    ask(event) {
        event.preventDefault();

        const input = this.inputEl;
        const submit = this.submitEl;
        const question = input ? input.value.trim() : '';
        if (!question) return;

        this.appendEntry('question', question);
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
            .then(r => r.json())
            .then(data => this.appendEntry('answer', data.answer ?? data.error ?? '', data.sources))
            .catch(() => this.appendEntry('answer', ''))
            .finally(() => {
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
                const link = document.createElement('a');
                link.href = source.url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.textContent = source.label;
                list.appendChild(link);
            });
            log.appendChild(list);
        }

        log.scrollTop = log.scrollHeight;
    }
}
