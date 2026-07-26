/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Minimal chat log for the dashboard assistant page - posts to AiAssistantController::ask(), nothing
// is stored client-side beyond the current page's DOM (a reload clears the log)
//
// Deliberately not using Stimulus's `static targets`/`static values` sugar: with a camelCase identifier
// ("aiAssistant") Stimulus looks for the non-dasherized "data-aiassistant-*" attributes, which is easy
// to get wrong on the template side. Plain dataset/querySelector scoped to this.element sidesteps that,
// but it means the names below are the contract: _ai_assistant_widget.html.twig MUST write the
// dasherized "data-ai-assistant-*" form, or askUrl reads back empty and inputEl is null (a null input
// makes ask() return before fetch(), so the box looks dead with no request at all).
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

    // "sources" is a backend-agnostic {label, url} list (see AiAssistantClientInterface) - rendered as
    // plain links, built via DOM APIs (not innerHTML) since both text and sources come from the network
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
