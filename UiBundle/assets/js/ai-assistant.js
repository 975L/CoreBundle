/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Where the panel keeps its conversation and whether it is open: sessionStorage, so it lives as long as the tab and no longer, suffixed with the per-account key so the next admin in the same tab starts blank
const STORE = 'c975l-donovan';

// Enough to scroll back through, short enough that the panel never carries a whole day of questions
const MAX_ENTRIES = 20;

// Plain dataset/querySelector rather than Stimulus targets/values, whose camelCase identifier would want the non-dasherized "data-aiassistant-*"
export default class extends Controller {
    // The panel drawn on every admin page reloads with each menu click: what it said and whether it was open come back with it
    connect() {
        if (!this.persist) return;

        const stored = this.readStore();
        for (const entry of stored.entries) this.appendEntry(entry.kind, entry.text, entry.sources);

        this.panelEl = this.element.closest('[data-ai-assistant-panel]');
        if (!this.panelEl) return;

        this.boundPanelClick = this.onPanelClick.bind(this);
        this.panelEl.addEventListener('click', this.boundPanelClick);
        this.setOpen(stored.open);
    }

    disconnect() {
        if (this.panelEl) this.panelEl.removeEventListener('click', this.boundPanelClick);
    }

    // The per-account key (see DonovanWidgetProvider), empty when nothing is to be kept
    get persist() {
        return this.element.dataset.aiAssistantPersistValue || '';
    }

    get storeKey() {
        return `${STORE}.${this.persist}`;
    }

    get askUrl() {
        return this.element.dataset.aiAssistantAskUrlValue || '';
    }

    get csrfToken() {
        return this.element.dataset.aiAssistantCsrfTokenValue || '';
    }

    get filmLabel() {
        return this.element.dataset.aiAssistantFilmLabelValue || '';
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
                ? this.showAnswer(question, data.answer, data.sources)
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

    // Kept as a pair, once answered: a question cut off by a menu click or failed never comes back alone
    showAnswer(question, text, sources) {
        this.appendEntry('answer', text, sources);
        this.remember({ kind: 'question', text: question }, { kind: 'answer', text, sources });
    }

    // The toggle opens and closes, "clear" starts the conversation over - both outside this element, in the panel around it
    onPanelClick(event) {
        if (!(event.target instanceof Element)) return;

        if (event.target.closest('[data-ai-assistant-panel-toggle]')) {
            const body = this.panelEl.querySelector('.ai-assistant-panel__body');
            this.setOpen(body ? body.hidden : false, true);
        } else if (event.target.closest('[data-ai-assistant-panel-clear]')) {
            if (this.logEl) this.logEl.replaceChildren();
            this.writeStore({ ...this.readStore(), entries: [] });
        }
    }

    // Focus only on a click: restored open after a page load, the panel must leave the cursor to the form the reader came to fill
    setOpen(open, focus = false) {
        const body = this.panelEl.querySelector('.ai-assistant-panel__body');
        const toggle = this.panelEl.querySelector('[data-ai-assistant-panel-toggle]');
        if (body) body.hidden = !open;
        if (toggle) toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        this.writeStore({ ...this.readStore(), open });

        if (open) {
            if (this.logEl) this.logEl.scrollTop = this.logEl.scrollHeight;
            if (focus && this.inputEl) this.inputEl.focus();
        }
    }

    remember(...entries) {
        if (!this.persist) return;

        const stored = this.readStore();
        this.writeStore({ ...stored, entries: [...stored.entries, ...entries].slice(-MAX_ENTRIES) });
    }

    // Storage can be missing or refused (private window, blocked site data): the panel then simply forgets, it never breaks
    readStore() {
        try {
            const stored = JSON.parse(window.sessionStorage.getItem(this.storeKey) || '{}');

            return { open: true === stored.open, entries: Array.isArray(stored.entries) ? stored.entries : [] };
        } catch {
            return { open: false, entries: [] };
        }
    }

    writeStore(stored) {
        try {
            window.sessionStorage.setItem(this.storeKey, JSON.stringify(stored));
        } catch {
            // Nothing kept, nothing broken
        }
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
                // A parcours is shown as well as walked through: its film opens beside the button
                if (source.project && source.film) {
                    list.appendChild(document.createTextNode(' '));
                    list.appendChild(this.buildLink({ url: source.film, label: `(${this.filmLabel})` }));
                }
            });
            log.appendChild(list);
        }

        log.scrollTop = log.scrollHeight;
    }

    // A question the backend has never seen costs it a model call, so the wait is counted in seconds and needs to be visible: without this, a disabled field is the only sign anything is happening
    // "Clear" waits too: the answer still on its way would otherwise land in the conversation just emptied
    showPending() {
        const pending = this.pendingEl;
        if (pending) pending.classList.remove('d-none');
        this.setClearDisabled(true);
    }

    hidePending() {
        const pending = this.pendingEl;
        if (pending) pending.classList.add('d-none');
        this.setClearDisabled(false);
    }

    setClearDisabled(disabled) {
        const clear = this.panelEl?.querySelector('[data-ai-assistant-panel-clear]');
        if (clear) clear.disabled = disabled;
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
